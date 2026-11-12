<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->role === 'customer' && $user->customer_status !== 'active') {
            return response()->json(['message' => 'Your customer account is '.$user->customer_status.'. Please contact support.'], 403);
        }
        if ($user?->role === 'customer' && (! $user->last_activity_at || $user->last_activity_at->lt(now()->subMinutes(5)))) {
            $user->last_activity_at = now();
            $user->saveQuietly();
        }

        return $next($request);
    }
}
