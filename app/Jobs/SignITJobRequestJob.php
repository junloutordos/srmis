<?php

namespace App\Jobs;

use App\Models\ITJobRequest;
use App\Models\User;
use App\Services\DigitalSignatureService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SignITJobRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(
        private readonly User $signer,
        private readonly ITJobRequest $jobRequest,
        private readonly string $stage,
        private readonly string $title,
        private readonly string $contentHash,
    ) {}

    public function handle(DigitalSignatureService $sigService): void
    {
        try {
            $sigService->sign(
                signer:        $this->signer,
                signableType:  ITJobRequest::class,
                signableId:    $this->jobRequest->id,
                documentTitle: $this->title,
                contentToHash: $this->contentHash,
                metadata:      [
                    'stage'   => $this->stage,
                    'itjr_no' => $this->jobRequest->itjr_no,
                    'title'   => $this->jobRequest->title,
                ],
            );
        } catch (\Throwable $e) {
            logger()->error('ITJR digital sign failed', [
                'job_request_id' => $this->jobRequest->id,
                'stage'          => $this->stage,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
