<?php

namespace App\Mail;

use App\Models\ITJobRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ITJRTargetDateApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ITJobRequest $jobRequest,
        public string $approveUrl,
        public string $rejectUrl,
    ) {}

    public function build()
    {
        return $this->subject("Target Date Approval Needed: {$this->jobRequest->itjr_no}")
                    ->view('emails.itjr.target-date-approval');
    }
}
