<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Rate limiting aliases
        $middleware->alias([
            'throttle.auth' => \Illuminate\Routing\Middleware\ThrottleRequests::class.':5,1',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
