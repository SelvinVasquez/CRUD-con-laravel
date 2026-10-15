<?php

namespace App\Http\Controllers;

use App\Models\Pelicula;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PeliculaController extends Controller
{
    /**
     * Indica si el usuario ya tiene una película con el mismo título y año
     * (sin distinguir mayúsculas). $ignorarId excluye la película que se edita.
     */
    private function esDuplicada(int $userId, string $titulo, int $anio, ?int $ignorarId = null): bool
    {
        return Pelicula::where('user_id', $userId)
            ->whereRaw('LOWER(titulo) = ?', [mb_strtolower(trim($titulo))])
            ->where('anio', $anio)
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->exists();
    }

    private function respuestaDuplicada(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Error de validación',
            'errors' => ['titulo' => ['Esta película ya está en tu lista.']],
        ], 422);
    }

    /**
     * GET /api/peliculas
     */
    public function index(Request $request): JsonResponse
    {
        $peliculas = Pelicula::where('user_id', $request->user()->id)->get();

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
            'favorita' => 'nullable|boolean',
            'vista' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($this->esDuplicada($request->user()->id, $request->input('titulo'), (int) $request->input('anio'))) {
            return $this->respuestaDuplicada();
        }

        $pelicula = new Pelicula($validator->validated());
        $pelicula->user_id = $request->user()->id;
        $pelicula->visualizaciones = $pelicula->vista ? 1 : 0;
        $pelicula->save();

        return response()->json([
            'success' => true,
            'message' => 'Película creada correctamente',
            'data' => $pelicula,
        ], 201);
    }

    /**
     * GET /api/peliculas/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $pelicula = Pelicula::where('user_id', $request->user()->id)->find($id);

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
        $pelicula = Pelicula::where('user_id', $request->user()->id)->find($id);

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
            'favorita' => 'nullable|boolean',
            'vista' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $titulo = $request->input('titulo', $pelicula->titulo);
        $anio = (int) $request->input('anio', $pelicula->anio);

        if ($this->esDuplicada($request->user()->id, $titulo, $anio, $pelicula->id)) {
            return $this->respuestaDuplicada();
        }

        // Marcar como vista por primera vez suma una visualización al ranking público.
        if ($request->boolean('vista') && ! $pelicula->vista) {
            $pelicula->visualizaciones++;
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
    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($request->user()->tokenCant('peliculas.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para eliminar películas',
            ], 403);
        }

        $pelicula = Pelicula::where('user_id', $request->user()->id)->find($id);

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
