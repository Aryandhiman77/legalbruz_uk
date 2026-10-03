<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Ngrok and production reverse proxies terminate HTTPS before the
        // request reaches Laravel. Trust their forwarded scheme/host so
        // asset(), route(), and Vite generate public HTTPS URLs instead of
        // mixed-content http://localhost URLs.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));

        $middleware->web(append: [
            \App\Http\Middleware\TrackWebsiteVisitor::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'legacy.services' => \App\Http\Middleware\EnsureLegacyServicesEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
