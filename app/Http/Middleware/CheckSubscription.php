<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user(); // ✅ via request

        if (!$user) {
            return response()->json([
                'message' => 'Non authentifié.',
                'code'    => 'UNAUTHENTICATED',
            ], 401);
        }

        // ✅ Les locataires passent sans vérification d'abonnement
        if ($user->user_type === 'locataire') {
            return $next($request);
        }

        // Vérifier que le profil propriétaire existe
        $proprietaire = $user->proprietaire;

        if (!$proprietaire) {
            return response()->json([
                'message' => 'Profil propriétaire introuvable.',
                'code'    => 'UNAUTHORIZED',
            ], 401);
        }

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
                'message'     => 'Votre période d\'essai de 30 jours est terminée. Veuillez choisir un abonnement.',
                'code'        => 'TRIAL_EXPIRED',
                'trial_ended' => $proprietaire->trial_ends_at,
            ], 403);
        }

        // ❌ Abonnement expiré
        if ($proprietaire->subscription_status === 'expired') {
            return response()->json([
                'message'    => 'Votre abonnement a expiré. Veuillez le renouveler pour continuer.',
                'code'       => 'SUBSCRIPTION_EXPIRED',
                'expired_at' => $proprietaire->subscription_ends_at,
            ], 403);
        }

        // ❌ Abonnement annulé
        if ($proprietaire->subscription_status === 'cancelled') {
            return response()->json([
                'message' => 'Votre abonnement a été annulé. Souscrivez à un nouveau plan.',
                'code'    => 'SUBSCRIPTION_CANCELLED',
            ], 403);
        }

        // ❌ Cas inconnu / suspendu
        return response()->json([
            'message' => 'Votre compte est suspendu. Contactez le support.',
            'code'    => 'ACCOUNT_SUSPENDED',
        ], 403);
    }
}