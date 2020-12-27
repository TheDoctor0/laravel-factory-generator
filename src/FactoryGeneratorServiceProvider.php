<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator;

use Illuminate\Support\ServiceProvider;
use TheDoctor0\LaravelFactoryGenerator\Console\GenerateFactoryCommand;

class FactoryGeneratorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $viewPath = __DIR__ . '/../resources/views';

        $this->loadViewsFrom($viewPath, 'factory-generator');

        $this->commands([GenerateFactoryCommand::class]);
    }
}
