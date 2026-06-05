<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

$isLocal = ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'production') === 'local';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) use ($isLocal): void {
        if ($isLocal) {
            $middleware->validateCsrfTokens(except: [
                'livewire/update',
            ]);
        }
    })
    ->withExceptions(function (Exceptions $exceptions) use ($isLocal): void {
        if ($isLocal) {
            $exceptions->render(function (HttpExceptionInterface $exception) {
                if ($exception->getStatusCode() !== 419) {
                    return null;
                }

                if (request()->is('livewire/update')) {
                    return response()->noContent();
                }

                if (request()->is('lunar*')) {
                    return redirect('/dev/lunar-login');
                }

                return null;
            });
        }
    })->create();
