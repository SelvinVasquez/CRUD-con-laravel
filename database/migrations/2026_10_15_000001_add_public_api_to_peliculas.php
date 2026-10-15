<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * API pública para el proyecto grupal: categorías, reviews y contador de visualizaciones.
     * Las películas registradas por la API pública no tienen dueño (user_id nulo).
     */
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::table('peliculas', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreignId('categoria_id')->nullable()->after('genero')->constrained('categorias')->nullOnDelete();
            $table->unsignedInteger('visualizaciones')->default(0)->after('vista');
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelicula_id')->constrained()->cascadeOnDelete();
            $table->string('autor');
            $table->unsignedTinyInteger('calificacion');
            $table->text('comentario')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');

        Schema::table('peliculas', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropColumn(['categoria_id', 'visualizaciones']);
        });

        Schema::dropIfExists('categorias');
    }
};
