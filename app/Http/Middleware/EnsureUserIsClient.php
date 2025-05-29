<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Client\Client;

class EnsureUserIsClient
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth('sanctum')->user();

        // Check if user is authenticated and is a Client instance
        if (!$user || !($user instanceof Client)) {
            return response()->json([
                'message' => 'Access denied. Client authentication required.'
            ], 403);
        }

        return $next($request);
    }
}
