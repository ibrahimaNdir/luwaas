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
        // Version simple : mail brut. Plus tard tu mettras une vraie Mailable.
        Mail::raw(
            "Votre code de vérification Luwaas est : {$otp}. Il est valable 10 minutes.",
            function ($message) use ($recipient) {
                $message->to($recipient)
                        ->subject('Code de vérification Luwaas');
            }
        );
    }
}