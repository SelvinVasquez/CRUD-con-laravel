<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pelicula extends Model
{
    use HasFactory;

    /**
     * Los campos que se pueden asignar de forma masiva.
     */
    protected $fillable = [
        'titulo',
        'sinopsis',
        'anio',
        'genero',
        'poster_url',
        'calificacion',
    ];

    /**
     * Conversión de tipos.
     */
    protected $casts = [
        'anio' => 'integer',
        'calificacion' => 'decimal:1',
    ];
}
