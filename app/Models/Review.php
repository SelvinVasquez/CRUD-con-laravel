<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = ['autor', 'calificacion', 'comentario'];

    protected $casts = [
        'calificacion' => 'integer',
    ];

    public function pelicula(): BelongsTo
    {
        return $this->belongsTo(Pelicula::class);
    }
}
