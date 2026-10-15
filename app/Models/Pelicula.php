<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pelicula extends Model
{
    use HasFactory;

    /**
     * Los campos que se pueden asignar de forma masiva.
     * user_id y visualizaciones se asignan siempre desde el servidor.
     */
    protected $fillable = [
        'titulo',
        'sinopsis',
        'anio',
        'genero',
        'categoria_id',
        'poster_url',
        'calificacion',
        'favorita',
        'vista',
    ];

    /**
     * Conversión de tipos.
     */
    protected $casts = [
        'anio' => 'integer',
        'calificacion' => 'decimal:1',
        'favorita' => 'boolean',
        'vista' => 'boolean',
        'visualizaciones' => 'integer',
        'reviews_avg_calificacion' => 'float',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
