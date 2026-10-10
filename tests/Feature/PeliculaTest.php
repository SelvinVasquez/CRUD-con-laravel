<?php

use App\Models\Pelicula;
use App\Models\User;
use Laravel\Passport\Passport;

function nuevaPelicula(User $usuario, array $extra = []): Pelicula
{
    $pelicula = new Pelicula(array_merge([
        'titulo' => 'Mi película',
        'anio' => 2020,
        'genero' => 'Drama',
    ], $extra));
    $pelicula->user_id = $usuario->id;
    $pelicula->save();

    return $pelicula;
}

function autenticar(?User $usuario = null, array $scopes = ['peliculas.read', 'peliculas.write', 'peliculas.delete']): User
{
    $usuario ??= User::factory()->create();
    Passport::actingAs($usuario, $scopes);

    return $usuario;
}

test('exige token para listar películas', function () {
    $this->getJson('/api/peliculas')->assertUnauthorized();
});

test('lista solo las películas del usuario autenticado', function () {
    $yo = autenticar();
    nuevaPelicula($yo, ['titulo' => 'Mía']);
    nuevaPelicula(User::factory()->create(), ['titulo' => 'Ajena']);

    $this->getJson('/api/peliculas')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.titulo', 'Mía');
});

test('crea una película asociada al usuario', function () {
    $yo = autenticar();

    $this->postJson('/api/peliculas', [
        'titulo' => 'Nueva',
        'anio' => 2021,
        'genero' => 'Acción',
        'calificacion' => 8.5,
    ])->assertCreated()->assertJsonPath('data.user_id', $yo->id);
});

test('valida los datos al crear', function () {
    autenticar();

    $this->postJson('/api/peliculas', ['titulo' => '', 'anio' => 1700])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['titulo', 'anio', 'genero']);
});

test('no muestra ni edita películas de otro usuario', function () {
    autenticar();
    $ajena = nuevaPelicula(User::factory()->create());

    $this->getJson("/api/peliculas/{$ajena->id}")->assertNotFound();
    $this->putJson("/api/peliculas/{$ajena->id}", ['titulo' => 'Hackeada'])->assertNotFound();
    $this->deleteJson("/api/peliculas/{$ajena->id}")->assertNotFound();
});

test('actualiza solo los campos enviados', function () {
    $yo = autenticar();
    $pelicula = nuevaPelicula($yo);

    $this->putJson("/api/peliculas/{$pelicula->id}", ['favorita' => true])
        ->assertOk()
        ->assertJsonPath('data.favorita', true)
        ->assertJsonPath('data.titulo', 'Mi película');
});

test('elimina una película propia', function () {
    $yo = autenticar();
    $pelicula = nuevaPelicula($yo);

    $this->deleteJson("/api/peliculas/{$pelicula->id}")->assertOk();

    expect(Pelicula::find($pelicula->id))->toBeNull();
});

test('un token de solo lectura no puede crear ni eliminar', function () {
    $yo = autenticar(null, ['peliculas.read']);
    $pelicula = nuevaPelicula($yo);

    $this->postJson('/api/peliculas', ['titulo' => 'X', 'anio' => 2020, 'genero' => 'Drama'])
        ->assertForbidden();
    $this->deleteJson("/api/peliculas/{$pelicula->id}")->assertForbidden();
});
