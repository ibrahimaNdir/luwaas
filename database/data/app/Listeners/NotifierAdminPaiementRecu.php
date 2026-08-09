<?php

namespace App\Listeners;

use App\Events\PaiementAbonnementRecu;
use App\Models\Admin;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class NotifierAdminPaiementRecu
{
    public function __construct(protected NotificationService $notificationService) {}

    public function handle(PaiementAbonnementRecu $event): void
    {
        $subscription = $event->subscription;
        $proprietaire = $subscription->proprietaire?->user;
        $plan         = $subscription->plan;

        $montant = number_format($subscription->amount, 0, ',', ' ');
        $nomPlan = $plan ? strtoupper($plan->tier) : 'Inconnu';
        $nomUser = $proprietaire ? "{$proprietaire->prenom} {$proprietaire->nom}" : 'Inconnu';

        $admins = Admin::with('user')->get();

        foreach ($admins as $admin) {
            if (!$admin->user) continue;

            $this->notificationService->sendToUser(
                $admin->user,
                '💰 Paiement abonnement reçu',
                "{$montant} FCFA reçu de {$nomUser} (Plan {$nomPlan}).",
                'admin_paiement_abonnement',
                ['subscription_id' => $subscription->id, 'montant' => $subscription->amount]
            );
        }

        Log::info("🔔 Admins notifiés : paiement abonnement #{$subscription->id}");
    }
}
