<?php

namespace App\Services\Otp; 

interface OtpServiceInterface
{
    public function generateOtp(): string;

    public function sendOtp(string $recipient, string $otp): void;
} 
