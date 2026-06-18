<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expiry    = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $resetUrl  = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Reset Your Password — STRIDE')
            ->view('emails.auth.password_reset', [
                'resetUrl' => $resetUrl,
                'expiry'   => $expiry,
            ]);
    }
}
