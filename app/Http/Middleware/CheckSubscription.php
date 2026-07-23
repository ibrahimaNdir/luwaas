<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Non authentifié.',
                'code'    => 'UNAUTHENTICATED',
            ], 401);
        }

        // Locataires — aucune vérification d'abonnement
        if ($user->user_type === 'locataire') {
            return $next($request);
        }

        // Bailleur sans profil → bloqué
        $proprietaire = $user->proprietaire;

        if (! $proprietaire) {
            return response()->json([
                'message' => 'Profil propriétaire introuvable.',
                'code'    => 'UNAUTHORIZED',
            ], 401);
        }

        // Tout bailleur avec un profil valide accède au back-office,
        // quel que soit son statut d'abonnement.
        // Le blocage de publication est géré par CheckPublicationQuota.
        return $next($request);
    }
}