<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

beforeEach(function () {
    Cache::flush();
    config([
        'services.viajes.client_id' => 'id',
        'services.viajes.client_secret' => 'secreto',
        'services.perfiles.email' => 'admin@x.com',
        'services.perfiles.password' => 'clave123',
    ]);
});

test('exige login para usar las integraciones', function () {
    $this->getJson('/api/integraciones/viajes')->assertUnauthorized();
    $this->getJson('/api/integraciones/perfiles')->assertUnauthorized();
});

test('lista viajes completados usando el token OAuth de Lester', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'tok']),
        '*/completados/viajes*' => Http::response(['data' => [['id' => 1, 'destino' => 'Antigua']], 'meta' => []]),
    ]);
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/integraciones/viajes')
        ->assertOk()
        ->assertJsonPath('data.0.destino', 'Antigua');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'completados') && $r->hasHeader('Authorization', 'Bearer tok'));
});

test('registra un viaje forzando el estado completado', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'tok']),
        '*/completados/viajes' => Http::response(['data' => ['id' => 9]], 201),
    ]);
    Passport::actingAs(User::factory()->create());

    $this->postJson('/api/integraciones/viajes', [
        'destino' => 'Tikal', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-03', 'estado' => 'planeado',
    ])->assertCreated();

    Http::assertSent(fn ($r) => str_contains($r->url(), 'completados') && $r['estado'] === 'completado');
});

test('valida las fechas del viaje antes de llamar a la API externa', function () {
    Http::fake();
    Passport::actingAs(User::factory()->create());

    $this->postJson('/api/integraciones/viajes', [
        'destino' => 'Tikal', 'fecha_inicio' => '2026-09-05', 'fecha_fin' => '2026-09-01',
    ])->assertUnprocessable();

    Http::assertNothingSent();
});

test('lista perfiles con el login de administrador de Jose', function () {
    Http::fake([
        '*/auth/login' => Http::response(['token' => 'jwt']),
        '*/users' => Http::response([['id' => '1', 'name' => 'Ana', 'email' => 'a@a.com', 'role' => 'usuario']]),
    ]);
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/integraciones/perfiles')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Ana');
});

test('registra un perfil sin necesitar el token de administrador', function () {
    Http::fake(['*/auth/register' => Http::response(['token' => 't', 'user' => ['id' => '2']], 201)]);
    Passport::actingAs(User::factory()->create());

    $this->postJson('/api/integraciones/perfiles', [
        'name' => 'Luis', 'email' => 'luis@x.com', 'password' => 'secreto1',
    ])->assertCreated();
});

test('devuelve 503 si falta configurar las credenciales', function () {
    config(['services.viajes.client_secret' => null]);
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/integraciones/viajes')->assertStatus(503);
});
