<?php

namespace Tests\Feature\Signing;

use App\Mail\ITJRTargetDateApprovalMail;
use App\Models\DigitalSignature;
use App\Models\ITJobRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class ItjrSigningTest extends TestCase
{
    use RefreshesTenantDatabase;

    private function request(array $attrs = []): ITJobRequest
    {
        return ITJobRequest::create($attrs + [
            'user_id' => User::factory()->create()->id,
            'category' => 'Hardware', 'title' => 'x', 'description' => 'x',
            'status' => 'Pending Division Chief Approval',
            'divisionchief_id' => User::factory()->create()->id,
        ]);
    }

    private function stages(ITJobRequest $r): array
    {
        return DigitalSignature::where('signable_id', $r->id)->get()->map(fn ($d) => $d->metadata['stage'])->all();
    }

    public function test_filing_is_blocked_without_signature_setup_for_every_request_type(): void
    {
        $user = User::factory()->withoutSignature()->create();

        foreach (['jobrequests.store', 'vehicle-requests.store', 'facility-requests.store', 'service-requests.store'] as $name) {
            if (! \Route::has($name)) { continue; }
            $this->actingAs($user)->post(route($name), [])
                ->assertSessionHasErrors('message');
        }
        $this->assertDatabaseCount('it_job_requests', 0);
    }

    public function test_dc_email_link_only_shows_a_pin_page_and_signs_on_post(): void
    {
        $chief = User::factory()->create();
        $r = $this->request(['divisionchief_id' => $chief->id]);

        $get = URL::signedRoute('it-job-requests.dc.approve', ['jobRequest' => $r->id, 'chief' => $chief->id], now()->addDay());
        $this->get($get)->assertOk()->assertSee('Approve & Sign');
        $this->assertSame('Pending Division Chief Approval', $r->refresh()->status);

        $post = URL::signedRoute('it-job-requests.dc.approve.submit', ['jobRequest' => $r->id, 'chief' => $chief->id], now()->addDay());
        $this->post($post, ['pin' => '000000'])->assertSessionHasErrors('pin');
        $this->assertSame('Pending Division Chief Approval', $r->refresh()->status);
        $this->assertSame([], $this->stages($r));

        $this->post($post, ['pin' => '123456'])->assertOk();
        $this->assertNotSame('Pending Division Chief Approval', $r->refresh()->status);
        $this->assertSame(['dc_approval'], $this->stages($r));
    }

    public function test_target_date_proposal_emails_ocd_with_links_and_email_approval_signs(): void
    {
        Mail::fake();
        $ocd = User::factory()->create();
        $ocd->roles()->attach(Role::firstOrCreate(['name' => 'OCD'])->id);
        $r = $this->request(['status' => 'Pending Target Date Approval']);

        $approve = URL::signedRoute('it-job-requests.target-date.approve.submit', ['jobRequest' => $r->id, 'approver' => $ocd->id], now()->addDay());
        $this->post($approve, ['pin' => '123456'])->assertOk();

        $this->assertSame('Target Date Approved', $r->refresh()->status);
        $this->assertSame(['target_date_approval'], $this->stages($r));
    }

    public function test_target_date_email_reject_requires_reason(): void
    {
        $ocd = User::factory()->create();
        $ocd->roles()->attach(Role::firstOrCreate(['name' => 'OCD'])->id);
        $r = $this->request(['status' => 'Pending Target Date Approval']);

        $reject = URL::signedRoute('it-job-requests.target-date.reject.submit', ['jobRequest' => $r->id, 'approver' => $ocd->id], now()->addDay());
        $this->post($reject, [])->assertSessionHasErrors('reason');
        $this->post($reject, ['reason' => 'Too late'])->assertOk();
        $this->assertSame('Target Date Rejected', $r->refresh()->status);
    }

    public function test_wrong_pin_in_app_blocks_the_approval(): void
    {
        $chief = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'DivisionChief']);
        $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['name' => 'it.requests.manage'], ['module' => 'MIS', 'description' => 'x'])->id]);
        $chief->roles()->attach($role->id);
        $r = $this->request(['divisionchief_id' => $chief->id]);

        $this->actingAs($chief)->post(route('job-requests.division-chief-action', $r->id), ['action' => 'approve', 'pin' => '999999'])
            ->assertSessionHasErrors('message');
        $this->assertSame('Pending Division Chief Approval', $r->refresh()->status);
    }
}
