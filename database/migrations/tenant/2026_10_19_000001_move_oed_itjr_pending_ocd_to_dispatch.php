<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * OED dropped the pre-dispatch OCD approval stage for IT Job Requests
 * (Division Chief -> Dispatcher directly). Requests already waiting at
 * "Pending OCD Approval" would be stranded, so move them to the dispatch queue.
 * Other tenants keep the OCD stage and are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (tenant('id') !== 'oed') {
            return;
        }

        DB::table('it_job_requests')
            ->where('status', 'Pending OCD Approval')
            ->update([
                'status'     => 'Pending Dispatch',
                'queued_at'  => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Irreversible data move.
    }
};
