<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    /**
     * Marque l'utilisateur comme "en ligne" dans le cache pour 5 minutes.
     * Utilisé par l'admin pour voir combien d'utilisateurs sont actifs.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $userId = $request->user()->id;

            Cache::put("online_user_{$userId}", now()->toDateTimeString(), now()->addMinutes(5));

            $registry = Cache::get('online_user_ids', []);
            $cutoff   = now()->subMinutes(5)->timestamp;
            $registry = collect($registry)
                ->filter(fn ($timestamp) => $timestamp >= $cutoff)
                ->all();
            $registry[$userId] = now()->timestamp;
            Cache::put('online_user_ids', $registry, now()->addMinutes(10));
        }

        return $next($request);
    }
}
