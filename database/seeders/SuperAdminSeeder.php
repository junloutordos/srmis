<?php

namespace Database\Seeders;

use App\Models\Central\CentralUser;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email    = env('SUPERADMIN_EMAIL');
        $password = env('SUPERADMIN_PASSWORD');

        if (! $email || ! $password) {
            return;
        }

        CentralUser::firstOrCreate(
            ['email' => $email],
            ['name' => env('SUPERADMIN_NAME', 'Administrator'), 'password' => $password]
        );
    }
}
