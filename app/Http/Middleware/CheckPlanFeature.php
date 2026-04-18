<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPlanFeature
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        $proprietaire = $request->user()->proprietaire;

        if (! $proprietaire->canUseFeature($feature)) {
            return response()->json([
                'message'     => "Cette fonctionnalité n'est pas disponible dans votre plan.",
                'code'        => 'FEATURE_NOT_AVAILABLE',
                'feature'     => $feature,
                'upgrade_url' => url('/plans'),
            ], 403);
        }

        return $next($request);
    }
}