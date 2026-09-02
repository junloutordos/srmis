<?php

namespace Tests\Feature\Dispatch;

use App\Models\Division;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

/**
 * Tests for the GSU vehicle dispatch workflow:
 * submission -> 'Pending GSU Assignment' -> GSU Dispatcher assigns a driver
 * and vehicle -> 'Pending Division Chief Approval'.
 */
class VehicleRequestGSUDispatchTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function userWithRoleAndPermission(string $roleName, string $permissionName, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $role = Role::firstOrCreate(['name' => $roleName]);
        $perm = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'GeneralServices', 'description' => $permissionName]
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $user->roles()->attach($role->id);

        return $user;
    }

    protected function driver(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['position' => 'Driver'], $attrs));
    }

    protected function vehicle(array $attrs = []): Vehicle
    {
        return Vehicle::create(array_merge([
            'name' => 'Van ' . uniqid(), 'status' => 'Available',
        ], $attrs));
    }

    protected function pendingGsuRequest(array $overrides = []): VehicleRequest
    {
        $requester = User::factory()->create();

        return VehicleRequest::create(array_merge([
            'requestor_id' => $requester->id,
            'purpose'      => 'Field trip',
            'destination'  => 'Manila',
            'date_needed'  => now()->addDay()->format('Y-m-d'),
            'time_of_departure' => '08:00:00',
            'eta'          => '17:00:00',
            'passengers'   => 2,
            'status'       => 'Pending GSU Assignment',
        ], $overrides));
    }

    // ── gsuDispatch() ─────────────────────────────────────────────────────────

    public function test_gsu_dispatch_returns_403_without_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('vehicle-requests.gsu-dispatch'))
            ->assertForbidden();
    }

    public function test_gsu_dispatch_returns_200_with_permission(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('GSU Dispatcher', 'vehicles.dispatch');

        $this->actingAs($dispatcher)
            ->get(route('vehicle-requests.gsu-dispatch'))
            ->assertOk();
    }

    public function test_gsu_dispatch_only_lists_pending_gsu_assignment_requests(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('GSU Dispatcher', 'vehicles.dispatch');

        $pending = $this->pendingGsuRequest(['purpose' => 'Should appear']);
        $this->pendingGsuRequest(['purpose' => 'Already with DC', 'status' => 'Pending Division Chief Approval']);
        $this->pendingGsuRequest(['purpose' => 'Declined', 'status' => 'Declined']);

        $response = $this->actingAs($dispatcher)->get(route('vehicle-requests.gsu-dispatch'));

        $response->assertOk();
        $response->assertInertia(function ($page) use ($pending) {
            $items = $page->toArray()['props']['requests']['data'];
            $this->assertCount(1, $items);
            $this->assertSame($pending->id, $items[0]['id']);
            return true;
        });
    }

    // ── DriverController::assign() ───────────────────────────────────────────

    public function test_assign_returns_403_without_permission(): void
    {
        $user = User::factory()->create();
        $vehicleRequest = $this->pendingGsuRequest();
        $driver = $this->driver();
        $vehicle = $this->vehicle();

        $this->actingAs($user)
            ->postJson(route('vehicle-requests.assign-driver', $vehicleRequest->id), [
                'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id,
            ])
            ->assertForbidden();
    }

    public function test_assign_transitions_request_to_pending_division_chief_approval(): void
    {
        Mail::fake();
        $dispatcher = $this->userWithRoleAndPermission('GSU Dispatcher', 'vehicles.dispatch');
        $chief = User::factory()->create();
        $division = Division::create([
            'division_name' => 'Division A', 'acronym' => 'DA',
            'division_chief_id' => $chief->id, 'status' => 'active',
        ]);
        $requester = User::factory()->create(['division_id' => $division->id]);
        $vehicleRequest = $this->pendingGsuRequest([
            'requestor_id' => $requester->id, 'division_chief_id' => $chief->id,
        ]);
        $driver = $this->driver();
        $vehicle = $this->vehicle(['name' => 'Toyota Hiace']);

        $response = $this->actingAs($dispatcher)
            ->postJson(route('vehicle-requests.assign-driver', $vehicleRequest->id), [
                'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id,
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $vehicleRequest->refresh();
        $this->assertSame('Pending Division Chief Approval', $vehicleRequest->status);
        $this->assertSame($driver->id, $vehicleRequest->driver_id);
        $this->assertSame('Toyota Hiace', $vehicleRequest->vehicle_type);
    }

    public function test_assign_rejects_a_request_not_awaiting_gsu_assignment(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('GSU Dispatcher', 'vehicles.dispatch');
        $vehicleRequest = $this->pendingGsuRequest(['status' => 'Pending Division Chief Approval']);
        $driver = $this->driver();
        $vehicle = $this->vehicle();

        $response = $this->actingAs($dispatcher)
            ->postJson(route('vehicle-requests.assign-driver', $vehicleRequest->id), [
                'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id,
            ]);

        $response->assertStatus(422);
        $this->assertSame('Pending Division Chief Approval', $vehicleRequest->refresh()->status);
    }

    public function test_assign_requires_both_driver_and_vehicle(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('GSU Dispatcher', 'vehicles.dispatch');
        $vehicleRequest = $this->pendingGsuRequest();

        $response = $this->actingAs($dispatcher)
            ->postJson(route('vehicle-requests.assign-driver', $vehicleRequest->id), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['driver_id', 'vehicle_id']);
    }

    public function test_assign_detects_vehicle_double_booking_conflict(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('GSU Dispatcher', 'vehicles.dispatch');
        $vehicle = $this->vehicle(['name' => 'Toyota Hiace']);
        $driver = $this->driver();

        // Existing booking for the same vehicle, same date, overlapping time —
        // already past the GSU stage (e.g. approved by DC) so it counts as booked.
        VehicleRequest::create([
            'requestor_id' => User::factory()->create()->id,
            'purpose' => 'Existing trip', 'destination' => 'Baguio',
            'date_needed' => now()->addDay()->format('Y-m-d'),
            'time_of_departure' => '09:00:00', 'eta' => '12:00:00',
            'passengers' => 1, 'vehicle_type' => 'Toyota Hiace',
            'status' => 'Pending Division Chief Approval',
        ]);

        $vehicleRequest = $this->pendingGsuRequest([
            'date_needed' => now()->addDay()->format('Y-m-d'),
            'time_of_departure' => '10:00:00', 'eta' => '11:00:00',
        ]);

        $response = $this->actingAs($dispatcher)
            ->postJson(route('vehicle-requests.assign-driver', $vehicleRequest->id), [
                'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id,
            ]);

        $response->assertStatus(422);
        $response->assertJson(['type' => 'vehicle']);
        $this->assertSame('Pending GSU Assignment', $vehicleRequest->refresh()->status);
    }

    public function test_assign_detects_driver_double_booking_conflict(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('GSU Dispatcher', 'vehicles.dispatch');
        $driver = $this->driver();
        $vehicleA = $this->vehicle(['name' => 'Vehicle A']);
        $vehicleB = $this->vehicle(['name' => 'Vehicle B']);

        VehicleRequest::create([
            'requestor_id' => User::factory()->create()->id,
            'purpose' => 'Existing trip', 'destination' => 'Baguio',
            'date_needed' => now()->addDay()->format('Y-m-d'),
            'time_of_departure' => '09:00:00', 'eta' => '12:00:00',
            'passengers' => 1, 'driver_id' => $driver->id, 'vehicle_type' => 'Vehicle A',
            'status' => 'Pending Division Chief Approval',
        ]);

        $vehicleRequest = $this->pendingGsuRequest([
            'date_needed' => now()->addDay()->format('Y-m-d'),
            'time_of_departure' => '10:00:00', 'eta' => '11:00:00',
        ]);

        $response = $this->actingAs($dispatcher)
            ->postJson(route('vehicle-requests.assign-driver', $vehicleRequest->id), [
                'driver_id' => $driver->id, 'vehicle_id' => $vehicleB->id,
            ]);

        $response->assertStatus(422);
        $response->assertJson(['type' => 'driver']);
        $this->assertSame('Pending GSU Assignment', $vehicleRequest->refresh()->status);
    }

    public function test_assign_allows_same_vehicle_on_non_overlapping_time(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('GSU Dispatcher', 'vehicles.dispatch');
        $vehicle = $this->vehicle(['name' => 'Toyota Hiace']);
        $driver = $this->driver();

        VehicleRequest::create([
            'requestor_id' => User::factory()->create()->id,
            'purpose' => 'Morning trip', 'destination' => 'Baguio',
            'date_needed' => now()->addDay()->format('Y-m-d'),
            'time_of_departure' => '06:00:00', 'eta' => '08:00:00',
            'passengers' => 1, 'vehicle_type' => 'Toyota Hiace',
            'status' => 'Pending Division Chief Approval',
        ]);

        $vehicleRequest = $this->pendingGsuRequest([
            'date_needed' => now()->addDay()->format('Y-m-d'),
            'time_of_departure' => '13:00:00', 'eta' => '17:00:00',
        ]);

        $response = $this->actingAs($dispatcher)
            ->postJson(route('vehicle-requests.assign-driver', $vehicleRequest->id), [
                'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id,
            ]);

        $response->assertOk();
        $this->assertSame('Pending Division Chief Approval', $vehicleRequest->refresh()->status);
    }

    // ── store() — submission starts at 'Pending GSU Assignment' ─────────────

    public function test_store_creates_request_pending_gsu_assignment_with_auto_resolved_chief(): void
    {
        $chief = User::factory()->create();
        $division = Division::create([
            'division_name' => 'Division B', 'acronym' => 'DB',
            'division_chief_id' => $chief->id, 'status' => 'active',
        ]);
        $requester = User::factory()->create(['division_id' => $division->id]);

        $response = $this->actingAs($requester)->post(route('vehicle-requests.store'), [
            'purpose' => 'Conference', 'destination' => 'Cebu',
            'date_needed' => [now()->addDay()->format('Y-m-d')],
            'time_of_departure' => '08:00', 'eta' => '17:00',
            'passengers' => 3,
        ]);

        $response->assertSessionHasNoErrors();

        $vr = VehicleRequest::where('requestor_id', $requester->id)->first();
        $this->assertNotNull($vr);
        $this->assertSame('Pending GSU Assignment', $vr->status);
        $this->assertSame($chief->id, $vr->division_chief_id);
    }

    public function test_store_fails_when_requester_has_no_division_chief(): void
    {
        $requester = User::factory()->create(['division_id' => null]);

        $response = $this->actingAs($requester)->post(route('vehicle-requests.store'), [
            'purpose' => 'Conference', 'destination' => 'Cebu',
            'date_needed' => [now()->addDay()->format('Y-m-d')],
            'time_of_departure' => '08:00', 'eta' => '17:00',
            'passengers' => 3,
        ]);

        $response->assertSessionHasErrors('division_chief_id');
        $this->assertDatabaseCount('vehicle_requests', 0);
    }
}
