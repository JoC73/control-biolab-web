<?php

use App\Http\Middleware\RequireBiolabAuth;
use App\Http\Middleware\RequireBiolabPermission;
use App\Http\Middleware\RequireBiolabRole;
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
        $middleware->alias([
            'biolab.auth' => RequireBiolabAuth::class,
            'biolab.role' => RequireBiolabRole::class,
            'biolab.permission' => RequireBiolabPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
