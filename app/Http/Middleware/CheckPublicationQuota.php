<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckPublicationQuota
{
    public function handle(Request $request, Closure $next)
    {
        // Log the request for debugging
        $user = $request->user();
        $proprietaire = $user?->proprietaire;

        // Refresh the proprietaire to get updated relationships (especially logements count)
        if ($proprietaire) {
            $proprietaire = $proprietaire->fresh();
        }

        Log::info('CheckPublicationQuota middleware called', [
            'method' => $request->method(),
            'path' => $request->path(),
            'statut_publication' => $request->input('statut_publication'),
            'user_id' => $user->id ?? null,
            'proprietaire_id' => $proprietaire->id ?? null,
        ]);

        // Si on ne publie pas → laisser passer directement
        if ($request->input('statut_publication') !== 'publie') {
            Log::info('CheckPublicationQuota: Not publishing, letting request through');
            return $next($request);
        }

        Log::info('CheckPublicationQuota: Checking publication quota');

        if (! $proprietaire) {
            Log::warning('CheckPublicationQuota: No proprietaire found for user');
            return response()->json([
                'message' => 'Profil propriétaire introuvable.',
                'code'    => 'UNAUTHORIZED',
            ], 401);
        }

        // Debug: Check current publication count and limits
        $plan = $proprietaire->resolvedPlan();
        $publishedCount = $proprietaire->logements()
            ->where('statut_publication', 'publie')
            ->whereDoesntHave('bails', fn ($q) => $q->where('status', 'actif'))
            ->count();

        Log::debug('CheckPublicationQuota: Debug info', [
            'plan_id' => $plan->id ?? null,
            'plan_publications_max' => $plan->publications_max ?? null,
            'publishedCount' => $publishedCount,
            'canPublishLogement' => $proprietaire->canPublishLogement(),
            'hasActiveSubscription' => $proprietaire->hasActiveSubscription(),
        ]);

        // Cas 1 : Abonnement actif (Starter ou Pro) → vérifier seulement le quota du plan
        if ($proprietaire->hasActiveSubscription()) {
            Log::info('CheckPublicationQuota: Active subscription check');

            if (! $proprietaire->canPublishLogement()) {
                Log::warning('CheckPublicationQuota: User has reached publish limit');

                $message = 'Vous avez atteint la limite de publications de votre plan ' . ucfirst($proprietaire->plan) . '.';
                if ($proprietaire->plan === 'starter') {
                    $message .= ' Passez au plan Pro pour publier davantage.';
                }

                return response()->json([
                    'message'     => $message,
                    'code'        => 'PUBLISH_LIMIT_REACHED',
                    'upgrade_url' => ($proprietaire->plan === 'starter') ? url('/plans') : null,
                    'limits'      => $proprietaire->planLimits(),
                ], 403);
            }

            Log::info('CheckPublicationQuota: User can publish, letting request through');
            return $next($request);
        }

        // Cas 2 : Aucun abonnement actif → blocage de publication
        Log::warning('CheckPublicationQuota: No active subscription, blocking publication');
        return response()->json([
            'message'          => 'Vous n\'avez pas d\'abonnement actif pour publier des logements. ',
            'code'             => 'NO_ACTIVE_SUBSCRIPTION',
            'upgrade_required' => true,
            'upgrade_url'      => url('/plans'),
            'limits'           => $proprietaire->planLimits(),
        ], 403);
    }
}