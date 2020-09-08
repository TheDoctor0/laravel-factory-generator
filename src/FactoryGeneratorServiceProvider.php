<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Support\DeferrableProvider;
use TheDoctor0\LaravelFactoryGenerator\Console\GenerateCommand;

class FactoryGeneratorServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot(): void
    {
        $viewPath = __DIR__ . '/../resources/views';

        $this->loadViewsFrom($viewPath, 'test-factory-helper');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind('command.test-factory-helper.generate',
            function ($app) {
                return new GenerateCommand($app['files'], $app['view']);
            }
        );

        $this->commands('command.test-factory-helper.generate');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['command.test-factory-helper.generate'];
    }

}
