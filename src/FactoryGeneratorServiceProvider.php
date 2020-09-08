<?php

namespace TheDoctor0\LaravelFactoryGenerator;

use Illuminate\Support\ServiceProvider;
use TheDoctor0\LaravelFactoryGenerator\Console\GenerateCommand;

class FactoryGeneratorServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $viewPath = __DIR__.'/../resources/views';
        $this->loadViewsFrom($viewPath, 'test-factory-helper');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
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
    public function provides()
    {
        return ['command.test-factory-helper.generate'];
    }

}
