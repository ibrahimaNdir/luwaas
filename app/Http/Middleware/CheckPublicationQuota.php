<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPublicationQuota
{
    public function handle(Request $request, Closure $next)
    {
        // Si on ne publie pas → laisser passer directement
        if ($request->input('statut_publication') !== 'publie') {
            return $next($request);
        }

        $proprietaire = $request->user()->proprietaire;

        // Cas 1 : Pro actif → vérifier seulement le quota du plan
        if ($proprietaire->hasActiveSubscription()) {
            if (! $proprietaire->canPublishLogement()) {
                return response()->json([
                    'message'     => 'Vous avez atteint la limite de publications de votre plan Pro.',
                    'code'        => 'PUBLISH_LIMIT_REACHED',
                    'upgrade_url' => url('/plans'),
                    'limits'      => $proprietaire->planLimits(),
                ], 403);
            }

            return $next($request);
        }

        // Cas 2 : Trial gratuit actif → vérifier le quota de 1 publication active
        if ($proprietaire->isFreeTrialActive()) {
            if (! $proprietaire->canPublishLogement()) {
                return response()->json([
                    'message'     => 'Vous avez déjà 1 logement publié et disponible. Passez au plan Pro pour publier davantage.',
                    'code'        => 'FREE_TRIAL_PUBLISH_LIMIT_REACHED',
                    'upgrade_required' => true,
                    'upgrade_url' => url('/plans'),
                    'limits'      => $proprietaire->planLimits(),
                ], 403);
            }

            return $next($request);
        }

        // Cas 3 : Trial expiré sans paiement → blocage total de publication
        return response()->json([
            'message'          => 'Votre accès gratuit de 15 jours a expiré. Passez au plan Pro pour publier un logement.',
            'code'             => 'FREE_TRIAL_EXPIRED',
            'upgrade_required' => true,
            'upgrade_url'      => url('/plans'),
            'trial_ended_at'   => $proprietaire->trial_ends_at,
            'limits'           => $proprietaire->planLimits(),
        ], 403);
    }
}