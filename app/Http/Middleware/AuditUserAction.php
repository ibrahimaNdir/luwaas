<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;

class AuditUserAction
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): \Illuminate\Http\Response  $next
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only log for authenticated users (optional)
        if (Auth::check()) {
            ActivityLog::create([
                'description' => 'User accessed '.$request->path().' with '.$request->method(),
                'properties'  => [
                    'route'             => $request->path(),
                    'method'            => $request->method(),
                    'ip'                => $request->ip(),
                    'user_agent'        => $request->header('User-Agent'),
                    'request_data'      => $request->all(), // be careful with large payloads; you may filter
                    'response_status'   => $response->getStatusCode(),
                ],
                'user_id'     => Auth::id(),
            ]);
        }

        return $response;
    }
}
