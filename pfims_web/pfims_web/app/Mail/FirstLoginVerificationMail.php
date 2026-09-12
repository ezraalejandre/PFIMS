<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FirstLoginVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $otp, public string $name = '') {}

    public function build(): static
    {
        return $this->subject('Your First Login Verification Code')
            ->view('first-login-verification');
    }
}
