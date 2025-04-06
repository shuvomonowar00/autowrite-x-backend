<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class RememberMeDuration
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // This part executes before the controller

        // Process the request
        $response = $next($request);

        // This part executes after the controller

        // Only apply to auth routes or routes that need session config
        if ($request->is('api/client/login') || $request->is('api/admin/login') || $request->is('api/vendor/login')) {
            $remember = $request->boolean('remember_me', false);

            // Store the remember value in session to use it after authentication
            $request->session()->put('auth_remember_requested', $remember);

            if ($remember) {
                // Set duration based on user type
                if ($request->is('api/client/login')) {
                    $expiration = 10080; // 7 days
                } elseif ($request->is('api/admin/login')) {
                    $expiration = 43200; // 30 days
                } elseif ($request->is('api/vendor/login')) {
                    $expiration = 20160; // 14 days
                }

                // Apply configuration before authentication happens
                Config::set('session.lifetime', $expiration);
                Config::set('sanctum.expiration', $expiration);
                Config::set('session.expire_on_close', false);

                // Store in session for later
                $request->session()->put('remember_expiration', $expiration);
            } else {
                // Standard session (2 hours, expire on close)
                Config::set('session.lifetime', 120);
                Config::set('sanctum.expiration', 120);
                Config::set('session.expire_on_close', true);
            }
        }

        // Check for existing authenticated sessions
        if (Auth::guard('clients')->check() && Auth::guard('clients')->viaRemember()) {
            // User was authenticated via remember token, extend session
            Config::set('session.lifetime', $request->session()->get('remember_expiration', 10080));
            Config::set('sanctum.expiration', $request->session()->get('remember_expiration', 10080));
            Config::set('session.expire_on_close', false);
        }
        // } elseif (Auth::guard('admin')->check() && Auth::guard('admin')->viaRemember()) {
        //     Config::set('session.lifetime', $request->session()->get('remember_expiration', 43200));
        //     Config::set('sanctum.expiration', $request->session()->get('remember_expiration', 43200));
        //     Config::set('session.expire_on_close', false);
        // } elseif (Auth::guard('vendors')->check() && Auth::guard('vendors')->viaRemember()) {
        //     Config::set('session.lifetime', $request->session()->get('remember_expiration', 20160));
        //     Config::set('sanctum.expiration', $request->session()->get('remember_expiration', 20160));
        //     Config::set('session.expire_on_close', false);
        // }

        // return $response;
        return $next($request);
    }
}
