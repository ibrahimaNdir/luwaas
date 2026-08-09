<?php

namespace App\Console\Commands;

use App\Models\Proprietaire;
use App\Models\Subscription;
use App\Notifications\SubscriptionExpiredNotification;
use App\Notifications\GracePeriodStartedNotification;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature   = 'subscriptions:expire';
    protected $description = 'Expire les abonnements Pro terminés et rétrograde les comptes en Starter';

    public function handle(): void
    {
        $this->expirePaidSubscriptions();
        $this->expirePendingSubscriptions();

        $this->info('✅ Traitement des abonnements terminé.');
    }

    // ─────────────────────────────────────────
    // 1. EXPIRER LES ABONNEMENTS PAYANTS TERMINÉS
    // ─────────────────────────────────────────

    private function expirePaidSubscriptions(): void
    {
        // 1. Basculer de active à grace_period
        $toGracePeriod = Proprietaire::where('subscription_status', 'active')
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<', now())
            ->get();

        foreach ($toGracePeriod as $proprietaire) {
            $proprietaire->update(['subscription_status' => 'grace_period']);

            // Marquer la facture/subscription comme expirée
            Subscription::where('proprietaire_id', $proprietaire->id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            try {
                $proprietaire->user->notify(new GracePeriodStartedNotification());
            } catch (\Exception $e) {
                $this->warn('Notification Grace Period échouée pour user_id: ' . $proprietaire->user_id);
            }
        }

        // 2. Basculer de grace_period à Starter (rétrogradation après 3 jours)
        $expired = Proprietaire::where('subscription_status', 'grace_period')
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<', now()->subDays(3))
            ->get();

        foreach ($expired as $proprietaire) {
            // Rétrogradation au lieu de suspension totale
            $proprietaire->update([
                'subscription_status'  => 'active',
                'plan'                 => 'starter',
                'billing_cycle'        => null,
                'subscription_ends_at' => null,
            ]);

            try {
                $proprietaire->user->notify(new SubscriptionExpiredNotification('paid'));
            } catch (\Exception $e) {
                $this->warn('Notification échouée pour user_id: ' . $proprietaire->user_id);
            }
        }

        $this->info("Abonnements basculés en grâce : {$toGracePeriod->count()}, rétrogradés en Starter : {$expired->count()}");
    }

    // ─────────────────────────────────────────
    // 2. EXPIRER LES PAIEMENTS ABANDONNÉS
    // ─────────────────────────────────────────

    private function expirePendingSubscriptions(): void
    {
        $expired = Subscription::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(30))
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update(['status' => 'expired']);
        }

        $this->info("Paiements abandonnés expirés : {$expired->count()}");
    }
}
