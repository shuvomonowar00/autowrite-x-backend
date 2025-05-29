<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Routing\Middleware\SubstituteBindings;
use App\Http\Middleware\RememberMeDuration;
use App\Http\Middleware\EnsureUserIsClient;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->use([
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Define rate limiting middleware alias
        // $middleware->alias([
        //     'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        // ]);

        $middleware->alias([
            'remember.duration' => RememberMeDuration::class,
            'client.auth' => EnsureUserIsClient::class,
            'Socialite' => Laravel\Socialite\Facades\Socialite::class,
        ]);

        // Configure middleware groups
        $middleware->group('web', [
            EnsureFrontendRequestsAreStateful::class,
            StartSession::class,
            ShareErrorsFromSession::class,
        ]);

        $middleware->group('api', [
            'throttle:api',
            SubstituteBindings::class,
        ]);

        $middleware->group('api.protected', [
            EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            RememberMeDuration::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
