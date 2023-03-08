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

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/factory-generator'),
        ], 'factory-generator');

        $this->loadViewsFrom(
            __DIR__ . '/../resources/views',
            'factory-generator',
        );

        $this->commands(GenerateFactoryCommand::class);
    }
}
