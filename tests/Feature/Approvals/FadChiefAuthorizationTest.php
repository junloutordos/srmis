<?php

namespace Tests\Feature\Approvals;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

/**
 * FAD authority comes from the "FAD Chief" role only — a job title that merely
 * contains "FAD" (e.g. "FAD Staff") must not grant approval rights.
 */
class FadChiefAuthorizationTest extends TestCase
{
    use RefreshesTenantDatabase;

    private function userWith(?string $roleName, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        if ($roleName) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $perm = Permission::firstOrCreate(
                ['name' => 'facilities.fad-approve'],
                ['module' => 'GeneralServices', 'description' => 'FAD approve']
            );
            $role->permissions()->syncWithoutDetaching([$perm->id]);
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    /** A holder of the permission whose only claim to FAD is the position text. */
    private function positionOnlyFad(): User
    {
        $user = $this->userWith('Staff', ['position' => 'FAD Staff']);
        $user->roles->first()->permissions()->syncWithoutDetaching([
            Permission::where('name', 'facilities.fad-approve')->value('id'),
        ]);

        return $user;
    }

    #[DataProvider('fadQueues')]
    public function test_position_text_alone_does_not_open_fad_queue(string $routeName): void
    {
        $this->actingAs($this->positionOnlyFad())->get(route($routeName))->assertForbidden();
    }

    #[DataProvider('fadQueues')]
    public function test_fad_chief_role_opens_fad_queue(string $routeName): void
    {
        $this->actingAs($this->userWith('FAD Chief'))->get(route($routeName))->assertOk();
    }

    public static function fadQueues(): array
    {
        return [
            'facility' => ['facility-requests.fad-approval'],
            'work'     => ['work-requests.fad-approval'],
            'service'  => ['service-requests.fad-approval'],
        ];
    }
}
