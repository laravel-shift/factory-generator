<?php

namespace Shift\FactoryGenerator;

use Illuminate\Support\ServiceProvider;
use Shift\FactoryGenerator\Commands\PrefillAll;
use Shift\FactoryGenerator\Commands\PrefillFactory;

class FactoryGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PrefillFactory::class,
                PrefillAll::class,
            ]);
        }

        $this->loadViewsFrom(__DIR__ . '/../stubs/views', 'prefill-factory-helper');
    }

    /**
     * Register services.
     */
    public function register()
    {
    }
}
