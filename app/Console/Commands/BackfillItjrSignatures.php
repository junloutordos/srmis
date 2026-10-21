<?php

namespace App\Console\Commands;

use App\Models\DigitalSignature;
use App\Models\ITJobRequest;
use App\Models\ITJRTrackingLog;
use App\Services\DigitalSignatureService;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Creates the missing DigitalSignature records for ITJR approvals that were
 * recorded in the tracking log but never signed (email-link approvals, and
 * approvals made without a PIN). Run per tenant:
 *   php artisan tenants:run "itjr:backfill-signatures" --tenants=oed        (dry run)
 *   php artisan tenants:run "itjr:backfill-signatures --apply" --tenants=oed
 */
class BackfillItjrSignatures extends Command
{
    protected $signature = 'itjr:backfill-signatures {--apply : Write the records (default is a dry run)}';
    protected $description = 'Backfill missing ITJR digital signatures from the tracking log';

    /** tracking-log status => signature stage */
    private const STAGES = [
        'Submitted IT Job Request' => 'submission',
        'Division Chief Approved'  => 'dc_approval',
        'OCD Approved'             => 'ocd_approval',
        'Target Date Approved'     => 'target_date_approval',
        'Acted by MIS'             => 'mis_acted',
        'Request Completed'        => 'completion',
    ];

    public function handle(DigitalSignatureService $sigService): int
    {
        $apply = (bool) $this->option('apply');
        $created = 0;

        foreach (ITJobRequest::orderBy('id')->cursor() as $jr) {
            $have = DigitalSignature::where('signable_type', ITJobRequest::class)
                ->where('signable_id', $jr->id)->get()
                ->map(fn ($d) => $d->metadata['stage'] ?? null)->filter()->all();

            foreach (self::STAGES as $status => $stage) {
                if (in_array($stage, $have, true)) {
                    continue;
                }

                $log = ITJRTrackingLog::where('it_job_request_id', $jr->id)->where('status', $status)->orderBy('id')->first();
                $signer = $log ? User::find($log->updated_by) : null;
                if (! $signer || empty($signer->electronic_signature)) {
                    continue;
                }

                $this->line(sprintf('%s %s  %s  by %s (#%d)', $apply ? 'SIGN' : 'WOULD SIGN', $jr->itjr_no, $stage, $signer->name, $signer->id));
                $created++;

                if ($apply) {
                    $record = $sigService->sign(
                        $signer, ITJobRequest::class, $jr->id,
                        "IT Job Request #{$jr->itjr_no} — {$stage} (backfilled)",
                        $jr->itjr_no . $stage . $signer->id,
                        ['stage' => $stage, 'itjr_no' => $jr->itjr_no, 'title' => $jr->title, 'backfilled' => true],
                    );
                    $record->forceFill(['signed_at' => $log->created_at])->save();
                }
            }
        }

        $this->info(($apply ? 'Created ' : 'Would create ') . $created . ' signature record(s).');

        return self::SUCCESS;
    }
}
