<?php

namespace Shift\FactoryGenerator;

use Illuminate\Support\ServiceProvider;

class FactoryGeneratorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateCommand::class,
            ]);
        }
    }
}
