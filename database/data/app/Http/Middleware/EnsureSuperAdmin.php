<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->user_type !== 'admin' || !$user->admin?->is_active) {
            return response()->json([
                'message' => 'Accès refusé. Super admin uniquement.'
            ], 403);
        }

        return $next($request);
    }
}