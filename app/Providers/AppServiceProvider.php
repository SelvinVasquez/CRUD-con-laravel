<?php

namespace App\Providers;

use Carbon\CarbonInterval;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Esta API crea tokens personales (createToken()), así que su expiración
        // se controla con personalAccessTokensExpireIn, no con tokensExpireIn.
        Passport::personalAccessTokensExpireIn(CarbonInterval::months(6));

        // Scopes disponibles para los tokens de la API
        Passport::tokensCan([
            'peliculas.read' => 'Ver listado y detalle de películas',
            'peliculas.write' => 'Crear y editar películas',
            'peliculas.delete' => 'Eliminar películas',
        ]);

        // Scope que recibe un token si el login no solicita ninguno en particular
        Passport::setDefaultScope([
            'peliculas.read',
            'peliculas.write',
            'peliculas.delete',
        ]);
    }
}
