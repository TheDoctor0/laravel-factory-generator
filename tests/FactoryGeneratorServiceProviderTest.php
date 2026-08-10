<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Tests;

use Mockery;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use TheDoctor0\LaravelFactoryGenerator\Console\GenerateFactoryCommand;
use TheDoctor0\LaravelFactoryGenerator\FactoryGeneratorServiceProvider;

class FactoryGeneratorServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_the_generate_factory_command(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('generate:factory', $commands);
        $this->assertInstanceOf(GenerateFactoryCommand::class, $commands['generate:factory']);
    }

    #[Test]
    public function it_publishes_and_loads_the_factory_views(): void
    {
        $paths = ServiceProvider::pathsToPublish(FactoryGeneratorServiceProvider::class, 'factory-generator');

        $this->assertNotEmpty($paths);
        $this->assertStringEndsWith('resources/views', str_replace('\\', '/', array_key_first($paths)));

        $this->assertTrue($this->app['view']->exists('factory-generator::factory'));
    }

    #[Test]
    public function it_does_nothing_outside_of_the_console(): void
    {
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('runningInConsole')->once()->andReturn(false);
        $app->shouldNotReceive('afterResolving');

        (new FactoryGeneratorServiceProvider($app))->boot();
    }
}
