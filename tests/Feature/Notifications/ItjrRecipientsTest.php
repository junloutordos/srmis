<?php

namespace Tests\Feature\Notifications;

use App\Models\Division;
use App\Models\ITJobRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\NotificationRecipients;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

/**
 * ITJR emails must reach only the intended, active person — never a
 * deactivated chief and never an arbitrary hard-coded user.
 */
class ItjrRecipientsTest extends TestCase
{
    use RefreshesTenantDatabase;

    private function requesterWithChief(array $chiefAttrs = []): array
    {
        $chief = User::factory()->create($chiefAttrs);
        $division = Division::create([
            'division_name'     => 'Division of ' . $chief->id,
            'acronym'           => 'D' . $chief->id,
            'division_chief_id' => $chief->id,
            'status'            => 'active',
        ]);

        return [User::factory()->create(['division_id' => $division->id]), $chief];
    }

    public function test_can_email_rejects_inactive_missing_and_blank_email(): void
    {
        $this->assertTrue(NotificationRecipients::canEmail(User::factory()->create(['status' => 'active'])));
        $this->assertFalse(NotificationRecipients::canEmail(User::factory()->create(['status' => 'inactive'])));
        $this->assertFalse(NotificationRecipients::canEmail(User::factory()->create(['email' => ''])));
        $this->assertFalse(NotificationRecipients::canEmail(null));
    }

    public function test_submission_emails_only_the_active_division_chief(): void
    {
        Mail::fake();
        [$requester, $chief] = $this->requesterWithChief(['status' => 'active']);
        $bystander = User::factory()->create();

        $this->actingAs($requester)->post(route('jobrequests.store'), [
            'category' => 'Hardware', 'title' => 'Dead PC', 'description' => 'x', 'pin' => '123456',
        ]);

        Mail::assertSent(\App\Mail\DivisionChiefITJRApprovalMail::class, fn ($m) => $m->hasTo($chief->email));
        Mail::assertNotSent(\App\Mail\DivisionChiefITJRApprovalMail::class, fn ($m) => $m->hasTo($bystander->email));
    }

    public function test_submission_is_refused_when_the_division_chief_is_inactive(): void
    {
        Mail::fake();
        [$requester] = $this->requesterWithChief(['status' => 'inactive']);

        $response = $this->actingAs($requester)->post(route('jobrequests.store'), [
            'category' => 'Hardware', 'title' => 'Dead PC', 'description' => 'x', 'pin' => '123456',
        ]);

        $response->assertSessionHasErrors('category');
        $this->assertDatabaseMissing('it_job_requests', ['title' => 'Dead PC']);
        Mail::assertNothingSent();
    }

    public function test_information_officer_lookup_ignores_inactive_holders(): void
    {
        $role = Role::firstOrCreate(['name' => 'InformationOfficer']);
        $gone = User::factory()->create(['status' => 'inactive']);
        $gone->roles()->attach($role->id);
        $active = User::factory()->create(['status' => 'active']);
        $active->roles()->attach($role->id);

        $this->assertSame([$active->id], NotificationRecipients::forRole('InformationOfficer')->pluck('id')->all());
    }

    public function test_requester_is_not_emailed_when_deactivated(): void
    {
        $requester = User::factory()->create(['status' => 'inactive']);
        $req = ITJobRequest::create([
            'user_id' => $requester->id, 'category' => 'Hardware', 'title' => 'x', 'description' => 'x',
            'status' => 'Pending Division Chief Approval', 'divisionchief_id' => User::factory()->create()->id,
        ]);

        $this->assertFalse(NotificationRecipients::canEmail($req->user));
    }
}
