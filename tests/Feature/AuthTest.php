<?php

use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    // Los tokens personales necesitan un cliente de Passport
    $this->artisan('passport:client', [
        '--personal' => true,
        '--name' => 'Pruebas',
        '--provider' => 'users',
    ]);
});

function datosUsuario(array $extra = []): array
{
    return array_merge([
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'password' => 'secreto1',
    ], $extra);
}

test('registra un usuario nuevo', function () {
    $this->postJson('/api/register', datosUsuario())
        ->assertCreated()
        ->assertJsonPath('success', true);

    expect(User::where('email', 'ana@example.com')->exists())->toBeTrue();
});

test('rechaza un correo repetido', function () {
    User::factory()->create(['email' => 'ana@example.com']);

    $this->postJson('/api/register', datosUsuario())
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('rechaza una contraseña de menos de 6 caracteres', function () {
    $this->postJson('/api/register', datosUsuario(['password' => '123']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('inicia sesión y devuelve un token', function () {
    User::factory()->create(['email' => 'ana@example.com', 'password' => 'secreto1']);

    $this->postJson('/api/login', ['email' => 'ana@example.com', 'password' => 'secreto1'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['user', 'token']]);
});

test('rechaza credenciales inválidas', function () {
    User::factory()->create(['email' => 'ana@example.com', 'password' => 'secreto1']);

    $this->postJson('/api/login', ['email' => 'ana@example.com', 'password' => 'incorrecta'])
        ->assertUnauthorized();
});

test('bloquea el login después de 5 intentos por minuto', function () {
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/login', ['email' => 'x@example.com', 'password' => 'mal']);
    }

    $this->postJson('/api/login', ['email' => 'x@example.com', 'password' => 'mal'])
        ->assertStatus(429);
});

test('no permite acceder al usuario sin token', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

test('la ruta de depuración token-info ya no existe', function () {
    Passport::actingAs(User::factory()->create(), ['peliculas.read']);

    $this->getJson('/api/token-info')->assertNotFound();
});
