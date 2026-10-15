<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Pelicula;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * API pública (sin login) para que otros compañeros del grupo consuman el catálogo:
 * listado general, registro de películas, categorías, ranking y reviews.
 */
class PublicoController extends Controller
{
    private function ok(string $mensaje, mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $mensaje, 'data' => $data], $status);
    }

    private function invalido($validator): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Error de validación',
            'errors' => $validator->errors(),
        ], 422);
    }

    private function noEncontrada(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Película no encontrada'], 404);
    }

    /** Campos públicos de una película (nunca se expone user_id). */
    private function consulta()
    {
        return Pelicula::query()
            ->select([
                'id', 'titulo', 'sinopsis', 'anio', 'genero', 'categoria_id', 'poster_url',
                'calificacion', 'visualizaciones', 'created_at',
            ])
            ->with('categoria:id,nombre')
            ->withCount('reviews')
            ->withAvg('reviews', 'calificacion');
    }

    /**
     * GET /api/publico/peliculas?q=&categoria_id=&anio=&per_page=
     */
    public function peliculas(Request $request): JsonResponse
    {
        $consulta = $this->consulta()->orderBy('titulo');

        if ($q = $request->query('q')) {
            $consulta->whereRaw('LOWER(titulo) LIKE ?', ['%'.mb_strtolower($q).'%']);
        }
        if ($categoria = $request->query('categoria_id')) {
            $consulta->where('categoria_id', (int) $categoria);
        }
        if ($anio = $request->query('anio')) {
            $consulta->where('anio', (int) $anio);
        }

        $porPagina = min(max((int) $request->query('per_page', 50), 1), 100);

        return $this->ok('Películas obtenidas correctamente', $consulta->paginate($porPagina));
    }

    /**
     * GET /api/publico/peliculas/{id}
     */
    public function pelicula(string $id): JsonResponse
    {
        $pelicula = $this->consulta()->find($id);

        return $pelicula
            ? $this->ok('Película obtenida correctamente', $pelicula)
            : $this->noEncontrada();
    }

    /**
     * POST /api/publico/peliculas
     */
    public function registrar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:255',
            'sinopsis' => 'nullable|string',
            'anio' => 'required|integer|min:1888',
            'genero' => 'required|string|max:255',
            'categoria_id' => 'nullable|integer|exists:categorias,id',
            'poster_url' => 'nullable|string|max:2048',
            'calificacion' => 'nullable|numeric|min:0|max:10',
        ]);

        if ($validator->fails()) {
            return $this->invalido($validator);
        }

        $datos = $validator->validated();

        $existe = Pelicula::whereNull('user_id')
            ->whereRaw('LOWER(titulo) = ?', [mb_strtolower(trim($datos['titulo']))])
            ->where('anio', $datos['anio'])
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => ['titulo' => ['Esta película ya está registrada.']],
            ], 422);
        }

        $pelicula = Pelicula::create($datos);

        return $this->ok('Película registrada correctamente', $this->consulta()->find($pelicula->id), 201);
    }

    /**
     * POST /api/publico/peliculas/{id}/visualizar  (suma una visualización)
     */
    public function visualizar(string $id): JsonResponse
    {
        $pelicula = Pelicula::find($id);

        if (! $pelicula) {
            return $this->noEncontrada();
        }

        $pelicula->increment('visualizaciones');

        return $this->ok('Visualización registrada', [
            'id' => $pelicula->id,
            'visualizaciones' => $pelicula->visualizaciones,
        ]);
    }

    /**
     * GET /api/publico/peliculas/ranking?orden=valoradas|vistas&limit=10
     */
    public function ranking(Request $request): JsonResponse
    {
        $orden = $request->query('orden', 'valoradas');

        if (! in_array($orden, ['valoradas', 'vistas'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => ['orden' => ['Usa "valoradas" o "vistas".']],
            ], 422);
        }

        $limite = min(max((int) $request->query('limit', 10), 1), 100);
        $consulta = $this->consulta();

        if ($orden === 'vistas') {
            $consulta->orderByDesc('visualizaciones')->orderBy('titulo');
        } else {
            $consulta->orderByRaw('calificacion IS NULL')->orderByDesc('calificacion')->orderByDesc('visualizaciones');
        }

        return $this->ok('Ranking obtenido correctamente', $consulta->limit($limite)->get());
    }

    /**
     * GET /api/publico/categorias
     */
    public function categorias(): JsonResponse
    {
        return $this->ok(
            'Categorías obtenidas correctamente',
            Categoria::withCount('peliculas')->orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    /**
     * POST /api/publico/categorias
     */
    public function crearCategoria(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100|unique:categorias,nombre',
        ]);

        if ($validator->fails()) {
            return $this->invalido($validator);
        }

        $categoria = Categoria::create($validator->validated());

        return $this->ok('Categoría creada correctamente', $categoria, 201);
    }

    /**
     * GET /api/publico/peliculas/{id}/reviews
     */
    public function reviews(string $id): JsonResponse
    {
        $pelicula = Pelicula::find($id);

        if (! $pelicula) {
            return $this->noEncontrada();
        }

        return $this->ok(
            'Reviews obtenidas correctamente',
            $pelicula->reviews()->latest()->get(['id', 'pelicula_id', 'autor', 'calificacion', 'comentario', 'created_at'])
        );
    }

    /**
     * POST /api/publico/peliculas/{id}/reviews
     */
    public function crearReview(Request $request, string $id): JsonResponse
    {
        $pelicula = Pelicula::find($id);

        if (! $pelicula) {
            return $this->noEncontrada();
        }

        $validator = Validator::make($request->all(), [
            'autor' => 'required|string|max:100',
            'calificacion' => 'required|integer|min:1|max:10',
            'comentario' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->invalido($validator);
        }

        $review = $pelicula->reviews()->create($validator->validated());

        return $this->ok('Review registrada correctamente', $review, 201);
    }
}
