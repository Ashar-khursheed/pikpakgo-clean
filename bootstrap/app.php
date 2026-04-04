<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // API-only app — never redirect unauthenticated users to a login route
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'user.type' => \App\Http\Middleware\CheckUserType::class,
            'admin'     => \App\Http\Middleware\CheckUserType::class,
            'verified'  => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Return JSON 401 for unauthenticated requests (JWT guard — no login route in API-only app)
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        });
        $exceptions->render(function (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e, $request) {
            return response()->json(['success' => false, 'message' => 'Token is invalid'], 401);
        });
        $exceptions->render(function (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e, $request) {
            return response()->json(['success' => false, 'message' => 'Token has expired'], 401);
        });
        $exceptions->render(function (\Tymon\JWTAuth\Exceptions\JWTException $e, $request) {
            return response()->json(['success' => false, 'message' => 'Token not provided'], 401);
        });
    })->create();
