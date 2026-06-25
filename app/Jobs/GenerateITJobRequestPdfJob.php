<?php

namespace App\Jobs;

use App\Models\ITJobRequest;
use App\Services\ITJobRequestPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateITJobRequestPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(
        private readonly ITJobRequest $jobRequest,
    ) {}

    public function handle(ITJobRequestPdfService $pdfService): void
    {
        try {
            $pdfService->generate($this->jobRequest);
        } catch (\Throwable $e) {
            logger()->error('Failed to generate IT Job Request PDF', [
                'job_request_id' => $this->jobRequest->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
