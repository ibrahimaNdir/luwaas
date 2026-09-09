<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Otp\OtpServiceInterface;
use App\Services\Otp\EmailOtpService;
use App\Services\Subscription\SubscriptionService;
use App\Contracts\SmsProviderInterface;
use App\Services\Sms\FakeSmsDriver;
use App\Contracts\PaymentGatewayInterface;
use App\Services\Gateways\PaydunyaGateway;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // OTP
        $this->app->bind(OtpServiceInterface::class, EmailOtpService::class);
        $this->app->singleton(SubscriptionService::class);

        // SMS
        $this->app->bind(SmsProviderInterface::class, FakeSmsDriver::class);

        // ──────────────────────────────────────────────────────────────
        // GATEWAY DE PAIEMENT
        // Pour changer d'agrégateur : remplacer PaydunyaGateway par
        // la nouvelle classe (ex: ByctorysGateway, CinetPayGateway).
        // UNE SEULE LIGNE à modifier. Aucun autre fichier ne change.
        // ──────────────────────────────────────────────────────────────
        $this->app->bind(PaymentGatewayInterface::class, PaydunyaGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}