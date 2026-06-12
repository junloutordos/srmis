<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Messengerial and Assets were removed from SRMIS. Drop their tables from
 * already-provisioned schemas (fresh tenants no longer create them) and
 * clean up the now-orphaned RBAC rows: messengerial.* / documents.*
 * permissions and the Records role that only existed for messengerial
 * proof-of-delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('messengerial_requests');
        Schema::dropIfExists('messengerial_sequences');
        Schema::dropIfExists('assets');

        // Orphaned permissions (pivot rows cascade via permission_role FK,
        // but delete explicitly in case the FK lacks ON DELETE CASCADE).
        $permissionIds = DB::table('permissions')
            ->where('name', 'like', 'messengerial.%')
            ->orWhere('name', 'like', 'documents.%')
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        // The Records role is purposeless without messengerial.
        $recordsId = DB::table('roles')->where('name', 'Records')->value('id');

        if ($recordsId !== null) {
            DB::table('permission_role')->where('role_id', $recordsId)->delete();
            DB::table('role_user')->where('role_id', $recordsId)->delete();
            DB::table('roles')->where('id', $recordsId)->delete();
        }
    }

    public function down(): void
    {
        // Intentionally irreversible — the module is removed from the codebase.
    }
};
