<?php

namespace Tests\Feature\Users;

use App\Models\Role;
use App\Models\User;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

/**
 * "Deleting" a user only sets status = inactive, and users.email is unique,
 * so recreating that email must point the admin at reactivation instead of
 * failing with an unexplained "already taken".
 */
class InactiveEmailTest extends TestCase
{
    use RefreshesTenantDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'Administrator'])->id);

        return $admin;
    }

    private function payload(string $email): array
    {
        return ['name' => 'Test Person', 'email' => $email];
    }

    public function test_creating_a_user_with_an_inactive_users_email_reports_the_inactive_account(): void
    {
        $inactive = User::factory()->create(['email' => 'gone@pshssystem.edu.ph', 'name' => 'Gone User', 'status' => 'inactive']);

        $response = $this->actingAs($this->admin())->post('/users', $this->payload('gone@pshssystem.edu.ph'));

        $response->assertSessionHasErrors(['email', 'inactive_user_id']);
        $this->assertSame((string) $inactive->id, session('errors')->first('inactive_user_id'));
        $this->assertStringContainsString('inactive', strtolower(session('errors')->first('email')));
        $this->assertStringContainsString('Gone User', session('errors')->first('email'));
        $this->assertSame(1, User::where('email', 'gone@pshssystem.edu.ph')->count());
    }

    public function test_inactive_email_match_is_case_insensitive(): void
    {
        User::factory()->create(['email' => 'gone@pshssystem.edu.ph', 'status' => 'inactive']);

        $this->actingAs($this->admin())->post('/users', $this->payload('Gone@PSHSSystem.edu.ph'))
            ->assertSessionHasErrors(['inactive_user_id']);
    }

    public function test_creating_a_user_with_an_active_users_email_gets_the_ordinary_error(): void
    {
        User::factory()->create(['email' => 'here@pshssystem.edu.ph', 'status' => 'active']);

        $this->actingAs($this->admin())->post('/users', $this->payload('here@pshssystem.edu.ph'))
            ->assertSessionHasErrors(['email'])
            ->assertSessionDoesntHaveErrors(['inactive_user_id']);
    }

    public function test_changing_a_users_email_to_an_inactive_users_email_reports_the_inactive_account(): void
    {
        $inactive = User::factory()->create(['email' => 'gone@pshssystem.edu.ph', 'status' => 'inactive']);
        $other = User::factory()->create();

        $this->actingAs($this->admin())->put("/users/{$other->id}", $this->payload('gone@pshssystem.edu.ph'))
            ->assertSessionHasErrors(['email', 'inactive_user_id']);
        $this->assertSame((string) $inactive->id, session('errors')->first('inactive_user_id'));
    }

    public function test_reactivating_the_user_restores_active_status(): void
    {
        $inactive = User::factory()->create(['status' => 'inactive']);

        $this->actingAs($this->admin())->post("/users/{$inactive->id}/activate")->assertRedirect();

        $this->assertSame('active', $inactive->fresh()->status);
    }
}
