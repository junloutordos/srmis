<?php

namespace Tests\Feature\Dispatch;

use App\Mail\ITJRDispatchMail;
use App\Models\ITJobRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

/**
 * Dispatching straight from the "awaiting dispatch" email: a signed GET shows
 * a one-page form, a signed POST dispatches to the chosen MIS personnel.
 */
class ITJobRequestEmailDispatchTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function dispatcher(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $role = Role::firstOrCreate(['name' => 'ITJR Dispatcher']);
        $perm = Permission::firstOrCreate(
            ['name' => 'it.requests.dispatch'],
            ['module' => 'MIS', 'description' => 'it.requests.dispatch']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $user->roles()->attach($role->id);

        return $user;
    }

    protected function misUser(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'MIS'])->id);

        return $user;
    }

    protected function pendingRequest(array $overrides = []): ITJobRequest
    {
        return ITJobRequest::create(array_merge([
            'user_id'          => User::factory()->create()->id,
            'category'         => 'Hardware',
            'title'            => 'Broken monitor',
            'description'      => 'Screen flickers',
            'status'           => 'Pending Dispatch',
            'divisionchief_id' => User::factory()->create()->id,
            'priority'         => 'normal',
            'queued_at'        => now(),
        ], $overrides));
    }

    protected function formUrl(ITJobRequest $req, User $dispatcher, $expires = null): string
    {
        return URL::temporarySignedRoute(
            'it-job-requests.dispatch.form',
            $expires ?? now()->addWeekdays(3),
            ['jobRequest' => $req->id, 'dispatcher' => $dispatcher->id]
        );
    }

    protected function submitUrl(ITJobRequest $req, User $dispatcher): string
    {
        return URL::temporarySignedRoute(
            'it-job-requests.dispatch.submit',
            now()->addWeekdays(3),
            ['jobRequest' => $req->id, 'dispatcher' => $dispatcher->id]
        );
    }

    public function test_form_renders_for_a_valid_signed_link_without_login(): void
    {
        $dispatcher = $this->dispatcher();
        $mis = $this->misUser();
        $req = $this->pendingRequest();

        $this->get($this->formUrl($req, $dispatcher))
            ->assertOk()
            ->assertSee($req->itjr_no)
            ->assertSee($mis->name);
    }

    public function test_form_rejects_a_tampered_signature(): void
    {
        $dispatcher = $this->dispatcher();
        $req = $this->pendingRequest();

        $this->get($this->formUrl($req, $dispatcher) . 'x')->assertForbidden();
    }

    public function test_form_rejects_an_expired_link(): void
    {
        $dispatcher = $this->dispatcher();
        $req = $this->pendingRequest();

        $this->get($this->formUrl($req, $dispatcher, now()->subMinute()))->assertForbidden();
    }

    public function test_form_rejects_a_user_without_the_dispatch_permission(): void
    {
        $nobody = User::factory()->create();
        $req = $this->pendingRequest();

        $this->get($this->formUrl($req, $nobody))->assertForbidden();
    }

    public function test_form_rejects_an_inactive_dispatcher(): void
    {
        $dispatcher = $this->dispatcher(['status' => 'inactive']);
        $req = $this->pendingRequest();

        $this->get($this->formUrl($req, $dispatcher))->assertForbidden();
    }

    public function test_form_shows_already_processed_when_no_longer_pending(): void
    {
        $dispatcher = $this->dispatcher();
        $req = $this->pendingRequest(['status' => 'In Progress']);

        $this->get($this->formUrl($req, $dispatcher))
            ->assertOk()
            ->assertSee('Already Processed');
    }

    public function test_submit_dispatches_to_the_chosen_mis_and_logs_it(): void
    {
        Mail::fake();
        $dispatcher = $this->dispatcher();
        $mis = $this->misUser();
        $req = $this->pendingRequest();

        $this->post($this->submitUrl($req, $dispatcher), [
            'assignedto' => $mis->id,
            'notes'      => 'Routine hardware issue.',
        ])->assertOk()->assertSee('Request Dispatched');

        $req->refresh();
        $this->assertSame('In Progress', $req->status);
        $this->assertSame($mis->id, $req->assignedto);
        $this->assertDatabaseHas('itjr_tracking_logs', [
            'it_job_request_id' => $req->id,
            'status'            => 'Dispatched to MIS',
            'updated_by'        => $dispatcher->id,
        ]);
    }

    public function test_submit_requires_a_triage_note(): void
    {
        $dispatcher = $this->dispatcher();
        $mis = $this->misUser();
        $req = $this->pendingRequest();

        $this->post($this->submitUrl($req, $dispatcher), ['assignedto' => $mis->id])
            ->assertSessionHasErrors('notes');

        $this->assertSame('Pending Dispatch', $req->refresh()->status);
    }

    public function test_submit_rejects_a_non_mis_assignee(): void
    {
        $dispatcher = $this->dispatcher();
        $notMis = User::factory()->create();
        $req = $this->pendingRequest();

        $this->post($this->submitUrl($req, $dispatcher), ['assignedto' => $notMis->id, 'notes' => 'x'])
            ->assertSessionHasErrors('assignedto');

        $this->assertSame('Pending Dispatch', $req->refresh()->status);
    }

    public function test_submit_rejects_a_tampered_signature(): void
    {
        $dispatcher = $this->dispatcher();
        $mis = $this->misUser();
        $req = $this->pendingRequest();

        $this->post($this->submitUrl($req, $dispatcher) . 'x', ['assignedto' => $mis->id, 'notes' => 'x'])
            ->assertForbidden();

        $this->assertSame('Pending Dispatch', $req->refresh()->status);
    }

    public function test_submit_rejects_a_user_without_the_dispatch_permission(): void
    {
        $nobody = User::factory()->create();
        $mis = $this->misUser();
        $req = $this->pendingRequest();

        $this->post($this->submitUrl($req, $nobody), ['assignedto' => $mis->id, 'notes' => 'x'])
            ->assertForbidden();

        $this->assertSame('Pending Dispatch', $req->refresh()->status);
    }

    public function test_a_second_submit_does_not_re_dispatch(): void
    {
        Mail::fake();
        $dispatcher = $this->dispatcher();
        $first = $this->misUser();
        $second = $this->misUser();
        $req = $this->pendingRequest();

        $this->post($this->submitUrl($req, $dispatcher), ['assignedto' => $first->id, 'notes' => 'x'])->assertOk();
        $this->post($this->submitUrl($req, $dispatcher), ['assignedto' => $second->id, 'notes' => 'x'])
            ->assertOk()->assertSee('Already Processed');

        $this->assertSame($first->id, $req->refresh()->assignedto);
    }

    public function test_dispatchers_get_the_dispatch_email_with_a_signed_link_not_the_requester_template(): void
    {
        Mail::fake();
        $dispatcher = $this->dispatcher();
        $ocd = User::factory()->create();
        $ocd->roles()->attach(Role::firstOrCreate(['name' => 'OCD'])->id);
        $perm = Permission::firstOrCreate(['name' => 'it.requests.ocd-approve'], ['module' => 'MIS', 'description' => 'x']);
        Role::where('name', 'OCD')->first()->permissions()->syncWithoutDetaching([$perm->id]);

        $req = $this->pendingRequest(['status' => 'Pending OCD Approval', 'divisionchief_id' => $ocd->id]);

        $this->actingAs($ocd)->post(route('job-requests.ocd-action', $req->id), ['action' => 'approve', 'pin' => '123456']);

        Mail::assertSent(ITJRDispatchMail::class, function (ITJRDispatchMail $mail) use ($dispatcher, $req) {
            return $mail->hasTo($dispatcher->email)
                && str_contains($mail->dispatchUrl, "dispatch/{$req->id}/{$dispatcher->id}")
                && str_contains($mail->dispatchUrl, 'signature=');
        });
    }

    public function test_dispatch_link_expires_in_three_weekdays(): void
    {
        // Friday -> Wednesday: the weekend does not count against the 3 days.
        Carbon::setTestNow(Carbon::parse('2026-10-23 10:00:00')); // a Friday
        Mail::fake();
        $dispatcher = $this->dispatcher();
        $ocd = User::factory()->create();
        $ocd->roles()->attach(Role::firstOrCreate(['name' => 'OCD'])->id);
        $perm = Permission::firstOrCreate(['name' => 'it.requests.ocd-approve'], ['module' => 'MIS', 'description' => 'x']);
        Role::where('name', 'OCD')->first()->permissions()->syncWithoutDetaching([$perm->id]);
        $req = $this->pendingRequest(['status' => 'Pending OCD Approval', 'divisionchief_id' => $ocd->id]);

        $this->actingAs($ocd)->post(route('job-requests.ocd-action', $req->id), ['action' => 'approve', 'pin' => '123456']);

        Mail::assertSent(ITJRDispatchMail::class, function (ITJRDispatchMail $mail) {
            parse_str(parse_url($mail->dispatchUrl, PHP_URL_QUERY), $q);
            return Carbon::createFromTimestamp((int) $q['expires'])->toDateString() === '2026-10-28';
        });

        Carbon::setTestNow();
    }
}
