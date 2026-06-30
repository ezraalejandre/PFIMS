<?php

namespace App\Notifications;

// app/Notifications/ForgotPasswordOtpNotification.php

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ForgotPasswordOtpNotification extends Notification
{
    use Queueable;

    public function __construct(public string $otp) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Your Password Reset Code')
            ->line('Your verification code for resetting your password is:')
            ->line("**{$this->otp}**")
            ->line('This code will expire in 10 minutes.')
            ->line('If you did not request this, no action is needed.');
    }
}