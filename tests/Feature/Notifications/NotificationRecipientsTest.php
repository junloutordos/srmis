<?php

namespace Tests\Feature\Notifications;

use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\NotificationRecipients;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class NotificationRecipientsTest extends TestCase
{
    use RefreshesTenantDatabase;

    private function userWithRole(string $roleName, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->roles()->attach(Role::firstOrCreate(['name' => $roleName])->id);

        return $user;
    }

    public function test_for_role_returns_only_active_holders_of_that_role(): void
    {
        $active   = $this->userWithRole('FAD Chief', ['status' => 'active']);
        $inactive = $this->userWithRole('FAD Chief', ['status' => 'inactive']);
        $other    = $this->userWithRole('Staff');

        $ids = NotificationRecipients::forRole('FAD Chief')->pluck('id')->all();

        $this->assertSame([$active->id], $ids);
        $this->assertNotContains($inactive->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_for_role_ignores_fad_in_position_text(): void
    {
        $chief = $this->userWithRole('FAD Chief');
        User::factory()->create(['position' => 'FAD Staff']);
        User::factory()->create(['position' => 'Assistant to the FAD Chief']);

        $this->assertSame([$chief->id], NotificationRecipients::forRole('FAD Chief')->pluck('id')->all());
    }

    public function test_for_role_skips_users_without_email(): void
    {
        $this->userWithRole('GSU Head', ['email' => '']);
        $withEmail = $this->userWithRole('GSU Head');

        $this->assertSame([$withEmail->id], NotificationRecipients::forRole('GSU Head')->pluck('id')->all());
    }

    public function test_for_any_role_returns_each_user_once(): void
    {
        $user = $this->userWithRole('GSU Head');
        $user->roles()->attach(Role::firstOrCreate(['name' => 'GSU Dispatcher'])->id);

        $this->assertSame([$user->id], NotificationRecipients::forAnyRole(['GSU Head', 'GSU Dispatcher'])->pluck('id')->all());
    }

    public function test_notify_user_skips_inactive_users(): void
    {
        $inactive = User::factory()->create(['status' => 'inactive']);

        NotificationService::notifyUser($inactive, 'IT Job Request', 'ITJR-1', 'Pending', '/x');

        $this->assertSame(0, $inactive->notifications()->count());
    }

    public function test_notify_user_still_delivers_to_active_users(): void
    {
        $active = User::factory()->create(['status' => 'active']);

        NotificationService::notifyUser($active, 'IT Job Request', 'ITJR-1', 'Pending', '/x');

        $this->assertSame(1, $active->notifications()->count());
    }
}
