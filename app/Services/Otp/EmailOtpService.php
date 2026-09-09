<?php  

namespace App\Services\Otp;

// app/Services/Otp/EmailOtpService.php


use Illuminate\Support\Facades\Mail;

class EmailOtpService implements OtpServiceInterface
{
    public function generateOtp(): string
    {
        return (string) random_int(100000, 999999);
    } 

    public function sendOtp(string $recipient, string $otp): void
    {
        \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Mail\OtpVerificationMail($otp));
    }
}