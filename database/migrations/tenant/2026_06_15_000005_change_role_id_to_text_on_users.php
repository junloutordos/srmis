<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors CRCMIS production migrations 2026_01_11_000010/000011: users.role_id
 * stores a comma-separated list of all assigned role IDs (read via FIND_IN_SET
 * and written by UserRoleController::sync), so it must be TEXT, not BIGINT.
 * The SRMIS tenant baseline was dumped before those migrations existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tenants provisioned before this fix have the old index; tenants
        // provisioned from the updated baseline already lack it.
        if (array_key_exists('users_role_id_foreign', Schema::getIndexes('users'))) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_role_id_foreign');
            });
        }

        DB::statement('ALTER TABLE `users` MODIFY `role_id` TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `users` MODIFY `role_id` BIGINT UNSIGNED NULL');

        if (! array_key_exists('users_role_id_foreign', Schema::getIndexes('users'))) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('role_id', 'users_role_id_foreign');
            });
        }
    }
};
