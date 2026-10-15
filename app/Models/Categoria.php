<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $fillable = ['nombre'];

    public function peliculas(): HasMany
    {
        return $this->hasMany(Pelicula::class);
    }
}
