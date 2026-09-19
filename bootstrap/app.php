<?php

use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Passport\Exceptions\MissingScopeException;
use Laravel\Passport\Http\Middleware\CheckToken;
use Laravel\Passport\Http\Middleware\CheckTokenForAnyScope;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'scope' => CheckToken::class, // requiere TODOS los scopes listados
            'scopes' => CheckTokenForAnyScope::class, // requiere AL MENOS UNO
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Passport convierte MissingScopeException en AccessDeniedHttpException
        // antes de que Laravel evalúe los renderers, así que se detecta aquí.
        $exceptions->render(function (AccessDeniedHttpException $e) {
            if ($e->getPrevious() instanceof MissingScopeException) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para realizar esta acción',
                ], 403);
            }
        });
    })->create();
