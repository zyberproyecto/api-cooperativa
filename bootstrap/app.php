<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Globales (CORS activo para que respete config/cors.php)
        $middleware->use([
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Aliases de middleware
        $middleware->alias([
            // Tu alias existente
            'is_admin' => \App\Http\Middleware\IsAdmin::class,

            // Necesarios para auth y abilities con Sanctum
            'auth'      => \Illuminate\Auth\Middleware\Authenticate::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,       // requiere TODAS
            'ability'   => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,  // requiere ALGUNA
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();