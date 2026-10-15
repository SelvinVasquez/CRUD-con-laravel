<?php

use App\Models\Categoria;
use App\Models\Pelicula;
use App\Models\User;

function peliculaPublica(array $extra = []): Pelicula
{
    $pelicula = new Pelicula();
    $pelicula->forceFill(array_merge([
        'titulo' => 'Película '.uniqid(),
        'anio' => 2020,
        'genero' => 'Drama',
    ], $extra));
    $pelicula->save();

    return $pelicula;
}

test('lista todas las películas sin login y sin exponer user_id', function () {
    $dueno = User::factory()->create();
    $propia = new Pelicula(['titulo' => 'De un usuario', 'anio' => 2019, 'genero' => 'Drama']);
    $propia->user_id = $dueno->id;
    $propia->save();
    peliculaPublica(['titulo' => 'Pública']);

    $respuesta = $this->getJson('/api/publico/peliculas')->assertOk();

    expect($respuesta->json('data.data'))->toHaveCount(2);
    expect($respuesta->json('data.data.0'))->not->toHaveKey('user_id');
});

test('registra una película sin login y sin dueño', function () {
    $categoria = Categoria::create(['nombre' => 'Ciencia ficción']);

    $this->postJson('/api/publico/peliculas', [
        'titulo' => 'Interestelar',
        'anio' => 2014,
        'genero' => 'Ciencia ficción',
        'categoria_id' => $categoria->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.titulo', 'Interestelar')
        ->assertJsonPath('data.categoria.nombre', 'Ciencia ficción');

    expect(Pelicula::first()->user_id)->toBeNull();
});

test('rechaza una película pública duplicada y datos inválidos', function () {
    peliculaPublica(['titulo' => 'Repetida', 'anio' => 2001]);

    $this->postJson('/api/publico/peliculas', ['titulo' => 'repetida', 'anio' => 2001, 'genero' => 'X'])
        ->assertUnprocessable();
    $this->postJson('/api/publico/peliculas', ['titulo' => 'Sin año'])->assertUnprocessable();
});

test('crea y lista categorías', function () {
    $this->postJson('/api/publico/categorias', ['nombre' => 'Acción'])->assertCreated();
    $this->postJson('/api/publico/categorias', ['nombre' => 'Acción'])->assertUnprocessable();

    $this->getJson('/api/publico/categorias')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nombre', 'Acción');
});

test('ranking por mejor valoradas y por más vistas', function () {
    peliculaPublica(['titulo' => 'Mala', 'calificacion' => 3, 'visualizaciones' => 90]);
    peliculaPublica(['titulo' => 'Buena', 'calificacion' => 9, 'visualizaciones' => 10]);
    peliculaPublica(['titulo' => 'Sin nota']);

    $this->getJson('/api/publico/peliculas/ranking?orden=valoradas')
        ->assertOk()
        ->assertJsonPath('data.0.titulo', 'Buena')
        ->assertJsonPath('data.2.titulo', 'Sin nota');

    $this->getJson('/api/publico/peliculas/ranking?orden=vistas')
        ->assertOk()
        ->assertJsonPath('data.0.titulo', 'Mala');

    $this->getJson('/api/publico/peliculas/ranking?orden=otra')->assertUnprocessable();
});

test('registra reviews y las devuelve con el promedio', function () {
    $pelicula = peliculaPublica();

    $this->postJson("/api/publico/peliculas/{$pelicula->id}/reviews", [
        'autor' => 'Ana', 'calificacion' => 8, 'comentario' => 'Muy buena',
    ])->assertCreated();
    $this->postJson("/api/publico/peliculas/{$pelicula->id}/reviews", [
        'autor' => 'Luis', 'calificacion' => 6,
    ])->assertCreated();

    $this->postJson("/api/publico/peliculas/{$pelicula->id}/reviews", ['autor' => 'X', 'calificacion' => 11])
        ->assertUnprocessable();
    $this->postJson('/api/publico/peliculas/9999/reviews', ['autor' => 'X', 'calificacion' => 5])
        ->assertNotFound();

    $this->getJson("/api/publico/peliculas/{$pelicula->id}/reviews")->assertOk()->assertJsonCount(2, 'data');
    $detalle = $this->getJson("/api/publico/peliculas/{$pelicula->id}")->assertOk();
    expect($detalle->json('data.reviews_count'))->toBe(2);
    expect($detalle->json('data.reviews_avg_calificacion'))->toEqual(7);
});

test('suma visualizaciones', function () {
    $pelicula = peliculaPublica();

    $this->postJson("/api/publico/peliculas/{$pelicula->id}/visualizar")
        ->assertOk()
        ->assertJsonPath('data.visualizaciones', 1);
});

test('marcar como vista suma una visualización al ranking', function () {
    $usuario = User::factory()->create();
    Laravel\Passport\Passport::actingAs($usuario, ['peliculas.read', 'peliculas.write']);
    $pelicula = new Pelicula(['titulo' => 'Mía', 'anio' => 2020, 'genero' => 'Drama']);
    $pelicula->user_id = $usuario->id;
    $pelicula->save();

    $this->putJson("/api/peliculas/{$pelicula->id}", ['vista' => true])->assertOk();

    expect($pelicula->fresh()->visualizaciones)->toBe(1);
});
