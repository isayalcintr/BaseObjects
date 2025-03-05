<?php

namespace isayalcintr\BaseObjects;

use Illuminate\Support\ServiceProvider;

class BaseObjectServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Paket yayınlama işlemi
        $this->publishes([
            __DIR__.'/BaseObject.php' => app_path('Objects/BaseObject.php'),
            __DIR__.'/BaseFilterObject.php' => app_path('Objects/BaseFilterObject.php'),
        ], 'base-objects');
    }

    public function register()
    {
        // Gerekli binding işlemleri buraya eklenebilir
    }
}