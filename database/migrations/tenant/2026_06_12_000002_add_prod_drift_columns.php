<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns that exist in CRCMIS production but were never captured in the
 * monolith's migration history (added directly in prod). The module code
 * reads/writes them, so the tenant baseline needs them too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicle_requests', 'driver_id')) {
                $table->unsignedBigInteger('driver_id')->nullable()->after('division_chief_id');
                $table->foreign('driver_id')->references('id')->on('users')->nullOnDelete();
            }
        });

        Schema::table('it_job_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('it_job_requests', 'ict_equipment_id')) {
                $table->unsignedBigInteger('ict_equipment_id')->nullable();
            }
            if (! Schema::hasColumn('it_job_requests', 'decline_reason')) {
                $table->text('decline_reason')->nullable();
            }
            if (! Schema::hasColumn('it_job_requests', 'declined_at')) {
                $table->timestamp('declined_at')->nullable();
            }
        });

        Schema::table('facility_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('facility_requests', 'requestor')) {
                $table->string('requestor')->nullable();
            }
            if (! Schema::hasColumn('facility_requests', 'unit')) {
                $table->string('unit')->nullable();
            }
            if (! Schema::hasColumn('facility_requests', 'email')) {
                $table->string('email')->nullable();
            }
        });

        Schema::table('work_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('work_requests', 'expected_completion_date')) {
                $table->date('expected_completion_date')->nullable();
            }
            if (! Schema::hasColumn('work_requests', 'action_taken')) {
                $table->text('action_taken')->nullable();
            }
            if (! Schema::hasColumn('work_requests', 'date_completed')) {
                $table->date('date_completed')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_requests', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->dropColumn('driver_id');
        });
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->dropColumn(['ict_equipment_id', 'decline_reason', 'declined_at']);
        });
        Schema::table('facility_requests', function (Blueprint $table) {
            $table->dropColumn(['requestor', 'unit', 'email']);
        });
        Schema::table('work_requests', function (Blueprint $table) {
            $table->dropColumn(['expected_completion_date', 'action_taken', 'date_completed']);
        });
    }
};
