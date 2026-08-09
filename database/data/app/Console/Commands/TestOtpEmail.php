<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestOtpEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:otp-email {email}';

    protected $description = 'Envoie un email OTP de test à une adresse donnée';

    public function handle(\App\Services\Otp\EmailOtpService $otpService)
    {
        $email = $this->argument('email');
        $otp = $otpService->generateOtp();
        
        $this->info("Envoi d'un email OTP de test à {$email} avec le code {$otp}...");
        
        $otpService->sendOtp($email, $otp);
        
        $this->info("Email envoyé avec succès ! Vérifiez votre outil de capture d'emails (Mailpit/Mailtrap).");
    }
}
