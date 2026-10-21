<?php

namespace App\Mail;

use App\Models\ITJobRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ITJRDispatchMail extends Mailable
{
    use Queueable, SerializesModels;

    public $jobRequest;
    public $dispatcher;
    public $dispatchUrl;
    public $note;
    public $approverRole;

    public function __construct(ITJobRequest $jobRequest, User $dispatcher, string $dispatchUrl, ?string $note = null, ?string $approverRole = null)
    {
        $this->jobRequest = $jobRequest;
        $this->dispatcher = $dispatcher;
        $this->dispatchUrl = $dispatchUrl;
        $this->note = $note;
        $this->approverRole = $approverRole;
    }

    public function build()
    {
        return $this->subject("IT Job Request Awaiting Dispatch: {$this->jobRequest->itjr_no}")
                    ->view('emails.itjr.dispatch');
    }
}
