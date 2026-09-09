<?php

namespace App\Console\Commands;

use App\Models\Proprietaire;
use App\Models\Subscription;
use App\Notifications\SubscriptionExpiredNotification;
use App\Notifications\ProChoiceNeededNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExpireSubscriptions extends Command
{
    protected $signature   = 'subscriptions:expire';
    protected $description = 'Expire les trials et abonnements terminés + envoie les notifications + gestion du dépassement de quota';

    public function handle(): void
    {
        $this->expirePaidSubscriptions();
        $this->expirePendingSubscriptions();
        //$this->notifyTrialEndingSoon();

        // Gestion du dépassement de quota après expiration
        $this->processExpirationLogic();

        $this->info('✅ Traitement des abonnements terminé.');
    }

    // ─────────────────────
    // 1. EXPIRER LES TRIALS TERMINÉS
    // ─────────────────────
    private function expireTrials(): void
    {
        $expired = Proprietaire::where('subscription_status', 'trial')
            ->where('trial_ends_at', '<', now())
            ->get();

        foreach ($expired as $proprietaire) {
            $proprietaire->update(['subscription_status' => 'expired']);

            // Marquer la première notification d’expiration si pas déjà faite
            if (is_null($proprietaire->expiration_notified_at)) {
                $proprietaire->expiration_notified_at = now();
                $proprietaire->save();
            }

            // Notifier le propriétaire (trial expiration)
            try {
                $proprietaire->user->notify(new SubscriptionExpiredNotification('trial'));
            } catch (\Exception $e) {
                $this->warn('Notification échouée pour user_id: ' . $proprietaire->user_id);
            }
        }

        $this->info("Trials expirés : {$expired->count()}");
    }

    // ─────────────────────
    // 2. EXPIRER LES ABONNEMENTS PAYANTS TERMINÉS
    // ─────────────────────
    private function expirePaidSubscriptions(): void
    {
        // 1. Basculer de active à grace_period
        $toGracePeriod = Proprietaire::where('subscription_status', 'active')
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<', now())
            ->get();

        foreach ($toGracePeriod as $proprietaire) {
            $proprietaire->update(['subscription_status' => 'grace_period']);

            // Marquer la première notification d’expiration si pas déjà faite
            if (is_null($proprietaire->expiration_notified_at)) {
                $proprietaire->expiration_notified_at = now();
                $proprietaire->save();
            }

            // Marquer la subscription en DB
            Subscription::where('proprietaire_id', $proprietaire->id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            // Notifier (expiration abonnement payant)
            try {
                $proprietaire->user->notify(new SubscriptionExpiredNotification('paid'));
            } catch (\Exception $e) {
                $this->warn('Notification échouée pour user_id: ' . $proprietaire->user_id);
            }
        }

        $this->info("Abonnements basculés en grâce : {$toGracePeriod->count()}, rétrogradés en Starter : {$expired->count()}");
    }

    // ─────────────────────
    // 3. AVERTIR LES TRIALS QUI EXPIRENT BIENTÔT
    // ─────────────────────
    // private function notifyTrialEndingSoon(): void
    // {
    //     // Propriétaires dont le trial expire dans exactement 5 jours
    //     $ending = Proprietaire::where('subscription_status', 'trial')
    //         ->where('trial_ends_at', '<=', now()->addDays(5))
    //         ->where('trial_ends_at', '>', now())
    //         ->get();
    //
    //     foreach ($ending as $proprietaire) {
    //         try {
    //             $proprietaire->user->notify(
    //                 new TrialEndingSoonNotification($proprietaire->trialDaysLeft())
    //             );
    //         } catch (\Exception $e) {
    //             $this->warn('Notification échouée pour user_id: ' . $proprietaire->user_id);
    //         }
    //     }
    //
    //     $this->info("Notifications trial bientôt expiré envoyées : {$ending->count()}");
    // }

    // ─────────────────────
    // 4. PAIEMENTS ABANDONNÉS EXPIRÉS
    // ─────────────────────
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

    // ─────────────────────
    // 5. GESTION DU DÉPASSMENT DE QUOTA ET NOTIFICATIONS DE CHOIX
    // ─────────────────────
    private function processExpirationLogic(): void
    {
        // Proprietaires dont l’abonnement est expiré ou annulé et qui ont déjà reçu la première notification
        $owners = Proprietaire::whereIn('subscription_status', ['expired', 'cancelled'])
            ->whereNotNull('expiration_notified_at')
            ->get();

        foreach ($owners as $owner) {
            // ----------------- FIRST NOTIFICATION (déjà faite via expire*) -----------------
            // Si pas encore de expiration_notified_at (devrait déjà être défini) – on définit juste au cas où
            if (is_null($owner->expiration_notified_at)) {
                $owner->expiration_notified_at = now();
                $owner->save();

                $owner->notify(new SubscriptionExpiredNotification(
                    'pro_to_starter',
                    null,
                    []
                ));
                Log::info("Première notification d’expiration envoyée au propriétaire {$owner->id}");
                continue; // on attend le délai de grâce avant les étapes suivantes
            }

            // ----------------- NOTIFICATION DE CHOIX (après 3 jours de grâce) -----------------
            if (!$owner->choice_notified_at &&
                $owner->expiration_notified_at->diffInHours(now()) >= 72) { // 3 jours

                $owner->choice_notified_at = now();
                $owner->save();

                $vacant = $owner->vacantPublishedLogements()->get();

                $owner->notify(new ProChoiceNeededNotification(
                    $vacant->pluck('id')->toArray(),
                    $vacant->map(function ($l) {
                        return [
                            'id'   => $l->id,
                            'numero' => $l->numero,
                            'adresse'=> $l->adresse,
                        ];
                    })->toArray()
                ));
                Log::info("Notification de choix envoyée au propriétaire {$owner->id}");
                continue; // on attend le choix du propriétaire ou le fallback
            }

            // ----------------- FALLBACK AUTOMATIQUE (après 5 jours total) -----------------
            if ($owner->expiration_notified_at && $owner->choice_notified_at &&
                is_null($owner->choice_made_at) &&
                $owner->expiration_notified_at->diffInHours(now()) >= 120) { // 5 jours

                // On garde les 5 logements vacants les plus récemment mis à jour
                $vacant = $owner->vacantPublishedLogements()
                    ->orderByDesc('updated_at')
                    ->get();

                $toKeep   = $vacant->take(5)->pluck('id');
                $toArchive= $vacant->skip(5);

                // Archiver le reste
                $toArchive->each->update([
                    'statut_publication' => 'archivé',
                    'archived_reason'    => 'subscription_expiration',
                    'archived_at'        => now(),
                ]);

                Log::info("Fallback d’archivage appliqué pour le propriétaire {$owner->id} : "
                    . "gardés ". $toKeep->count() . ", archivés ". $toArchive->count());

                // Réinitialisation des flags afin de ne pas re‑traiter ce propriétaire
                $owner->choice_made_at   = now();
                $owner->choice_notified_at = null;
                $owner->expiration_notified_at = null;
                $owner->save();

                // Notification optionnelle informant le propriétaire du résultat
                $owner->notify(
                    (new \Illuminate\Notifications\Messages\MailMessage())
                        ->subject('Votre délai de choix est terminé')
                        ->line('Le système a automatiquement archivé les logements excédentaires.')
                        ->line('Vous pouvez republier ces logements dès que votre abonnement sera renouvelé.')
                );
            }

            // ----------------- SI LE PROPRIÉTAIRE A FAIT UN CHOIX, RÉINITIALISER LES FLAGS -----------------
            if ($owner->choice_made_at) {
                $owner->expiration_notified_at = null;
                $owner->choice_notified_at = null;
                $owner->choice_made_at = null;
                $owner->save();
            }
        }
    }
}