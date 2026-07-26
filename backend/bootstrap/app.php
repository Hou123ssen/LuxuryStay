<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApiRequest = fn ($request): bool => $request->expectsJson() || $request->is('api/*');

        $exceptions->render(function (ModelNotFoundException $e, $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return response()->json([
                'message' => 'Resource not found.',
            ], 404);
        });

        $exceptions->render(function (HttpExceptionInterface $e, $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            $status = $e->getStatusCode();
            $message = match ($status) {
                404 => 'Resource not found.',
                403 => 'Forbidden.',
                429 => $e->getMessage() ?: 'Too many requests.',
                default => $e->getMessage() ?: 'Request failed.',
            };

            return response()->json([
                'message' => $message,
            ], $status, $e->getHeaders());
        });

        $exceptions->render(function (\Throwable $e, $request) use ($isApiRequest) {
            if (! $isApiRequest($request)
                || $e instanceof ValidationException
                || $e instanceof AuthenticationException
            ) {
                return null;
            }

            return response()->json([
                'message' => 'Server error.',
            ], 500);
        });
    })->create();
