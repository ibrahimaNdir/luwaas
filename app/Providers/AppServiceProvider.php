<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Otp\OtpServiceInterface;
use App\Services\Otp\EmailOtpService;
use App\Services\Subscription\SubscriptionService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // On dit à Laravel : quand tu vois OtpServiceInterface, injecte EmailOtpService
        $this->app->bind(OtpServiceInterface::class, EmailOtpService::class);
        $this->app->singleton(SubscriptionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}