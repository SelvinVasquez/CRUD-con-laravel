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
        'destino' => 'Tikal', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-03', 'descripcion' => 'Visita', 'estado' => 'planeado',
    ])->assertCreated();

    Http::assertSent(fn ($r) => str_contains($r->url(), 'completados') && $r['estado'] === 'completado');
});

test('valida las fechas del viaje antes de llamar a la API externa', function () {
    Http::fake();
    Passport::actingAs(User::factory()->create());

    $this->postJson('/api/integraciones/viajes', [
        'destino' => 'Tikal', 'descripcion' => 'x', 'fecha_inicio' => '2026-09-05', 'fecha_fin' => '2026-09-01',
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

test('pide el código de verificación al registrar un perfil', function () {
    Http::fake(['*/auth/register/request-code' => Http::response(['message' => 'Código enviado', 'expiresInMinutes' => 10], 202)]);
    Passport::actingAs(User::factory()->create());

    $this->postJson('/api/integraciones/perfiles', [
        'name' => 'Luis', 'email' => 'luis@x.com', 'password' => 'Secreto!1',
    ])->assertStatus(202)->assertJsonPath('data.expiresInMinutes', 10);
});

test('rechaza contraseñas que no cumplen la política de Jose', function () {
    Http::fake();
    Passport::actingAs(User::factory()->create());

    $this->postJson('/api/integraciones/perfiles', [
        'name' => 'Luis', 'email' => 'luis@x.com', 'password' => 'secreto1',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');

    Http::assertNothingSent();
});

test('verifica el código y no reenvía el token de sesión de Jose', function () {
    Http::fake(['*/auth/register/verify' => Http::response(['token' => 'jwt', 'user' => ['id' => '20', 'email' => 'luis@x.com']], 201)]);
    Passport::actingAs(User::factory()->create());

    $r = $this->postJson('/api/integraciones/perfiles/verificar', ['email' => 'luis@x.com', 'code' => '123456'])
        ->assertCreated()
        ->assertJsonPath('data.user.email', 'luis@x.com');

    expect($r->json('data'))->not->toHaveKey('token');
});

test('devuelve 503 si falta configurar las credenciales', function () {
    config(['services.viajes.client_secret' => null]);
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/integraciones/viajes')->assertStatus(503);
});
