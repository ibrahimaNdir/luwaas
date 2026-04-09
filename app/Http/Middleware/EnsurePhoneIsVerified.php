<?php
// app/Http/Middleware/EnsurePhoneIsVerified.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePhoneIsVerified
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->hasVerifiedPhone()) {
            return response()->json([
                'message' => 'Veuillez vérifier votre numéro de téléphone avant de continuer.',
                'code'    => 'PHONE_NOT_VERIFIED'
            ], 403);
        }

        return $next($request);
    }
}