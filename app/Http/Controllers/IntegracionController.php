<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Intermediario hacia las APIs de los compañeros (Lester: viajes, Jose: perfiles).
 * Así las credenciales OAuth/admin nunca llegan al navegador ni a la app.
 */
class IntegracionController extends Controller
{
    // ---- Viajes completados (API de Lester, Azure, OAuth client_credentials) ----

    public function viajes(Request $request): JsonResponse
    {
        return $this->llamar(function () use ($request) {
            return $this->viajesHttp()->get('/api/integraciones/v1/completados/viajes', [
                'per_page' => min((int) $request->query('per_page', 50), 50),
            ]);
        }, 'viajes');
    }

    public function crearViaje(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'destino' => ['required', 'string', 'max:255'],
            'pais' => ['nullable', 'string', 'max:255'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'presupuesto' => ['nullable', 'numeric', 'min:0'],
            'moneda' => ['nullable', 'string', 'max:5'],
        ]);
        $datos['estado'] = 'completado';

        return $this->llamar(
            fn () => $this->viajesHttp()->post('/api/integraciones/v1/completados/viajes', $datos),
            'viajes',
            201
        );
    }

    private function viajesHttp(): PendingRequest
    {
        $cfg = config('services.viajes');
        abort_if(! $cfg['client_id'] || ! $cfg['client_secret'], 503, 'Integración de viajes no configurada.');

        $token = Cache::remember('viajes_token', 3000, function () use ($cfg) {
            $r = Http::asForm()->acceptJson()->timeout(15)->post($cfg['url'].'/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'scope' => $cfg['scope'],
            ]);
            abort_if(! $r->successful() || ! $r->json('access_token'), 502, 'No se pudo autenticar con la API de viajes.');

            return $r->json('access_token');
        });

        return Http::baseUrl($cfg['url'])->withToken($token)->acceptJson()->timeout(15);
    }

    // ---- Perfiles (API de Jose, AWS, login con token) ----

    public function perfiles(): JsonResponse
    {
        return $this->llamar(fn () => $this->perfilesHttp()->get('/users'), 'perfiles');
    }

    public function crearPerfil(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
        ]);

        // El registro de la API de Jose es público: no necesita el token de administrador.
        return $this->llamar(
            fn () => Http::baseUrl(config('services.perfiles.url'))->acceptJson()->timeout(15)->post('/auth/register', $datos),
            'perfiles',
            201
        );
    }

    private function perfilesHttp(): PendingRequest
    {
        $cfg = config('services.perfiles');
        abort_if(! $cfg['email'] || ! $cfg['password'], 503, 'Integración de perfiles no configurada.');

        $token = Cache::remember('perfiles_token', 600, function () use ($cfg) {
            $r = Http::acceptJson()->timeout(15)->post($cfg['url'].'/auth/login', [
                'email' => $cfg['email'],
                'password' => $cfg['password'],
            ]);
            abort_if(! $r->successful() || ! $r->json('token'), 502, 'No se pudo autenticar con la API de perfiles.');

            return $r->json('token');
        });

        return Http::baseUrl($cfg['url'])->withToken($token)->acceptJson()->timeout(15);
    }

    // ---- Común ----

    /** Ejecuta la llamada y normaliza la respuesta a { success, message, data }. */
    private function llamar(callable $peticion, string $origen, int $okStatus = 200): JsonResponse
    {
        try {
            $r = $peticion();
        } catch (ConnectionException) {
            return response()->json(['success' => false, 'message' => "No se pudo conectar con la API de $origen."], 502);
        }

        // Token vencido o revocado: se descarta para que el siguiente intento pida otro.
        if (in_array($r->status(), [401, 403], true)) {
            Cache::forget($origen === 'viajes' ? 'viajes_token' : 'perfiles_token');
        }

        if (! $r->successful()) {
            $body = $r->json();

            return response()->json([
                'success' => false,
                'message' => is_array($body) && isset($body['message']) ? $body['message'] : "La API de $origen respondió con error {$r->status()}.",
                'errors' => is_array($body) ? ($body['errors'] ?? null) : null,
            ], $r->status() >= 500 ? 502 : $r->status());
        }

        $body = $r->json();
        $data = is_array($body) && array_key_exists('data', $body) ? $body['data'] : $body;
        // Nunca se reenvían al cliente tokens de sesión de la API externa.
        if (is_array($data)) {
            unset($data['token']);
        }

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data], $okStatus);
    }
}
