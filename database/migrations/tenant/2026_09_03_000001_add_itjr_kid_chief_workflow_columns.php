<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports the ITJR target-completion-date approval gate (Tech Support
 * proposes, KID Chief/OCD approves or rejects) and per-tenant role display
 * labels (OED shows its OCD role as "KID Chief").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'display_name')) {
                $table->string('display_name')->nullable()->after('name');
            }
        });

        Schema::table('it_job_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('it_job_requests', 'target_date_rejection_reason')) {
                $table->text('target_date_rejection_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->dropColumn('target_date_rejection_reason');
        });
    }
};
