<?php


namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// Group the otp mail actions.
class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;
    public string $name;

    // Set up the values this class needs.
    public function __construct(string $otp, string $name = '')
    {
        $this->otp = $otp;
        $this->name = $name;
    }

    // Build the otp mail output.
    public function build()
    {
        return $this->subject('Your Password Reset Code')
            ->view('emails.otp');
    }
}