<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// Group the first login verification mail actions.
class FirstLoginVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    // Set up the values this class needs.
    public function __construct(public string $otp, public string $name = '') {}

    // Build the first login verification mail output.
    public function build(): static
    {
        return $this->subject('Your First Login Verification Code')
            ->view('first-login-verification');
    }
}
