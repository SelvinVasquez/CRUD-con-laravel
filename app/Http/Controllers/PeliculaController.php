<?php

namespace App\Http\Controllers;

use App\Models\Pelicula;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PeliculaController extends Controller
{
    /**
     * GET /api/peliculas
     */
    public function index(): JsonResponse
    {
        $peliculas = Pelicula::all();

        return response()->json([
            'success' => true,
            'message' => 'Películas obtenidas correctamente',
            'data' => $peliculas,
        ], 200);
    }

    /**
     * POST /api/peliculas
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:255',
            'sinopsis' => 'nullable|string',
            'anio' => 'required|integer|min:1888',
            'genero' => 'required|string|max:255',
            'poster_url' => 'nullable|string|max:2048',
            'calificacion' => 'nullable|numeric|min:0|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $pelicula = Pelicula::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Película creada correctamente',
            'data' => $pelicula,
        ], 201);
    }

    /**
     * GET /api/peliculas/{id}
     */
    public function show(string $id): JsonResponse
    {
        $pelicula = Pelicula::find($id);

        if (! $pelicula) {
            return response()->json([
                'success' => false,
                'message' => 'Película no encontrada',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Película obtenida correctamente',
            'data' => $pelicula,
        ], 200);
    }

    /**
     * PUT/PATCH /api/peliculas/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $pelicula = Pelicula::find($id);

        if (! $pelicula) {
            return response()->json([
                'success' => false,
                'message' => 'Película no encontrada',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'titulo' => 'sometimes|required|string|max:255',
            'sinopsis' => 'nullable|string',
            'anio' => 'sometimes|required|integer|min:1888',
            'genero' => 'sometimes|required|string|max:255',
            'poster_url' => 'nullable|string|max:2048',
            'calificacion' => 'nullable|numeric|min:0|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $pelicula->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Película actualizada correctamente',
            'data' => $pelicula,
        ], 200);
    }

    /**
     * DELETE /api/peliculas/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $pelicula = Pelicula::find($id);

        if (! $pelicula) {
            return response()->json([
                'success' => false,
                'message' => 'Película no encontrada',
            ], 404);
        }

        $pelicula->delete();

        return response()->json([
            'success' => true,
            'message' => 'Película eliminada correctamente',
        ], 200);
    }
}
