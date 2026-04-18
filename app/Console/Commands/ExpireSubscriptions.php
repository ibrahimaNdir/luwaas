<?php

namespace App\Console\Commands;

use App\Models\Proprietaire;
use App\Models\Subscription;
use App\Notifications\SubscriptionExpiredNotification;
use App\Notifications\TrialEndingSoonNotification;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature   = 'subscriptions:expire';
    protected $description = 'Expire les trials et abonnements terminés + envoie les notifications';

    public function handle(): void
    {
        $this->expireTrials();
        $this->expirePaidSubscriptions();
        $this->notifyTrialEndingSoon();

        $this->info('✅ Traitement des abonnements terminé.');
    }

    // ─────────────────────────────────────────
    // 1. EXPIRER LES TRIALS TERMINÉS
    // ─────────────────────────────────────────

    private function expireTrials(): void
    {
        $expired = Proprietaire::where('subscription_status', 'trial')
            ->where('trial_ends_at', '<', now())
            ->get();

        foreach ($expired as $proprietaire) {
            $proprietaire->update(['subscription_status' => 'expired']);

            // Notifier le propriétaire
            try {
                $proprietaire->user->notify(new SubscriptionExpiredNotification('trial'));
            } catch (\Exception $e) {
                $this->warn('Notification échouée pour user_id: ' . $proprietaire->user_id);
            }
        }

        $this->info("Trials expirés : {$expired->count()}");
    }

    // ─────────────────────────────────────────
    // 2. EXPIRER LES ABONNEMENTS PAYANTS TERMINÉS
    // ─────────────────────────────────────────

    private function expirePaidSubscriptions(): void
    {
        $expired = Proprietaire::where('subscription_status', 'active')
            ->where('subscription_ends_at', '<', now())
            ->get();

        foreach ($expired as $proprietaire) {
            $proprietaire->update(['subscription_status' => 'expired']);

            // Marquer la subscription en DB
            Subscription::where('proprietaire_id', $proprietaire->id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            // Notifier
            try {
                $proprietaire->user->notify(new SubscriptionExpiredNotification('paid'));
            } catch (\Exception $e) {
                $this->warn('Notification échouée pour user_id: ' . $proprietaire->user_id);
            }
        }

        $this->info("Abonnements payants expirés : {$expired->count()}");
    }

    // ─────────────────────────────────────────
    // 3. AVERTIR LES TRIALS QUI EXPIRENT BIENTÔT
    // ─────────────────────────────────────────

    private function notifyTrialEndingSoon(): void
    {
        // Propriétaires dont le trial expire dans exactement 5 jours
        $ending = Proprietaire::where('subscription_status', 'trial')
            ->whereDate('trial_ends_at', now()->addDays(5)->toDateString())
            ->get();

        foreach ($ending as $proprietaire) {
            try {
                $proprietaire->user->notify(
                    new TrialEndingSoonNotification($proprietaire->trialDaysLeft())
                );
            } catch (\Exception $e) {
                $this->warn('Notification échouée pour user_id: ' . $proprietaire->user_id);
            }
        }

        $this->info("Notifications trial bientôt expiré envoyées : {$ending->count()}");
    }
}