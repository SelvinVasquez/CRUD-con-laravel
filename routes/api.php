<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PeliculaController;
use Illuminate\Support\Facades\Route;

// Rutas de autenticación (sin protección)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Genera automáticamente las 7 rutas RESTful, protegidas con Passport:
// GET    /api/peliculas           -> index
// POST   /api/peliculas           -> store
// GET    /api/peliculas/{id}      -> show
// PUT    /api/peliculas/{id}      -> update
// DELETE /api/peliculas/{id}      -> destroy
Route::apiResource('peliculas', PeliculaController::class)
    ->middleware('auth:api');
