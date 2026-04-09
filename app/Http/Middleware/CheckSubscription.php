<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        // Vérifier que c'est bien un proprietaire
        if (!$user || !$user->proprietaire) {
            return response()->json([
                'message' => 'Accès refusé.',
                'code'    => 'UNAUTHORIZED',
            ], 401);
        }

        $proprietaire = $user->proprietaire;

        // ✅ Encore en trial valide
        if ($proprietaire->isInTrial()) {
            return $next($request);
        }

        // ✅ Abonnement payant actif
        if ($proprietaire->hasActiveSubscription()) {
            return $next($request);
        }

        // ❌ Trial expiré sans abonnement
        if ($proprietaire->subscription_status === 'trial') {
            return response()->json([
                'message'      => 'Votre période d\'essai de 30 jours est terminée. Veuillez choisir un abonnement.',
                'code'         => 'TRIAL_EXPIRED',
                'trial_ended'  => $proprietaire->trial_ends_at,
            ], 403);
        }

        // ❌ Abonnement expiré
        if ($proprietaire->subscription_status === 'expired') {
            return response()->json([
                'message'          => 'Votre abonnement a expiré. Veuillez le renouveler pour continuer.',
                'code'             => 'SUBSCRIPTION_EXPIRED',
                'expired_at'       => $proprietaire->subscription_ends_at,
            ], 403);
        }

        // ❌ Compte annulé ou suspendu
        return response()->json([
            'message' => 'Votre compte est suspendu. Contactez le support.',
            'code'    => 'ACCOUNT_SUSPENDED',
        ], 403);
    }
}