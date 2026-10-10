<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PeliculaController;
use Illuminate\Support\Facades\Route;

// Rutas de autenticación (sin protección). Limitadas a 5 intentos por minuto
// para frenar ataques de fuerza bruta.
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:api')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

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
