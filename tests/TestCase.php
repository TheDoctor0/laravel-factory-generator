<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use TheDoctor0\LaravelFactoryGenerator\FactoryGeneratorServiceProvider;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [FactoryGeneratorServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
