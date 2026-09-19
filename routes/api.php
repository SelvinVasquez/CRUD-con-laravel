<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PeliculaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rutas de autenticación (sin protección)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Endpoint de debugging para inspeccionar los scopes del token actual.
    // Eliminar antes de pasar a producción.
    Route::get('/token-info', function (Request $request) {
        $token = $request->user()->token();

        return response()->json([
            'token_id' => $token->id,
            'scopes' => $token->scopes,
            'user' => $request->user()->only('id', 'name', 'email'),
            'expires' => $token->expires_at,
        ]);
    });

    // Genera automáticamente las 7 rutas RESTful, protegidas con Passport:
    // GET    /api/peliculas           -> index
    // POST   /api/peliculas           -> store
    // GET    /api/peliculas/{id}      -> show
    // PUT    /api/peliculas/{id}      -> update
    // DELETE /api/peliculas/{id}      -> destroy
    // destroy verifica el scope peliculas.delete manualmente con tokenCan() en el controlador
    Route::apiResource('peliculas', PeliculaController::class)
        ->middlewareFor(['index', 'show'], 'scope:peliculas.read')
        ->middlewareFor(['store', 'update'], 'scope:peliculas.write');
});
