<?php

namespace Lucena\Cielo\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;

class CieloServiceProvider extends ServiceProvider
{
    /**
    * Bootstrap services.
    *
    * @return void
    */
    public function boot(Router $router)
    {
        include __DIR__ . '/../Http/routes.php';
 
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'cielo');

        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'cielo');

    }

}