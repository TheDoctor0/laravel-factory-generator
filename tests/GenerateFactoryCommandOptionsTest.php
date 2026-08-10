<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Tests;

use Mockery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use TheDoctor0\LaravelFactoryGenerator\Console\GenerateFactoryCommand;
use TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures\AbstractVehicle;
use TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures\BrokenModel;
use TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures\Customer;
use TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures\HookedModel;
use TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures\Nested\Order;
use TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures\NotAModel;
use TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures\PrefixedModel;

class GenerateFactoryCommandOptionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        File::deleteDirectory($this->app->databasePath('factories'));
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.connections.other', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    #[Test]
    public function it_discovers_models_from_the_default_directory_when_no_model_is_given(): void
    {
        if (! class_exists(\App\Models\Customer::class, false)) {
            class_alias(Customer::class, \App\Models\Customer::class);
        }

        $modelsDir = $this->app->basePath('app/Models');
        File::ensureDirectoryExists($modelsDir);
        File::put($modelsDir . '/Customer.php', "<?php\n");

        $cwd = getcwd();
        chdir($this->app->basePath());

        try {
            $this->artisan('generate:factory')->assertExitCode(0);
        } finally {
            chdir($cwd);
            File::deleteDirectory($this->app->basePath('app'));
        }

        $this->assertFileExists($this->app->databasePath('factories/CustomerFactory.php'));
    }

    #[Test]
    public function it_resolves_a_model_name_without_namespace_against_the_model_directory(): void
    {
        $this->artisan('generate:factory', ['model' => ['Missing']])
            ->expectsOutputToContain('Unable to find App\Models\Missing class!')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_reports_a_missing_model_directory(): void
    {
        $this->artisan('generate:factory', ['--dir' => 'app/Missing'])
            ->expectsOutputToContain('Model directory does not exists.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_skips_existing_factories_unless_forced(): void
    {
        $path = $this->app->databasePath('factories/CustomerFactory.php');

        $this->artisan('generate:factory', ['model' => [Customer::class]])->assertExitCode(0);

        file_put_contents($path, 'customized');

        $this->artisan('generate:factory', ['model' => [Customer::class]])
            ->expectsOutputToContain("Model factory exists, use --force to overwrite: $path")
            ->assertExitCode(0);

        $this->assertSame('customized', file_get_contents($path));

        $this->artisan('generate:factory', ['model' => [Customer::class], '--force' => true])
            ->expectsOutputToContain("Model factory created: $path")
            ->assertExitCode(0);

        $this->assertStringContainsString('extends Factory', file_get_contents($path));
    }

    #[Test]
    public function it_generates_nested_factories_with_a_custom_namespace_recursively(): void
    {
        $this->artisan('generate:factory', [
            'model' => [Order::class],
            '--recursive' => true,
            '--namespace' => 'TheDoctor0\\LaravelFactoryGenerator\\Tests\\Fixtures',
        ])->assertExitCode(0);

        $path = $this->app->databasePath('factories/Nested/OrderFactory.php');

        $this->assertFileExists($path);

        $contents = file_get_contents($path);

        $this->assertStringContainsString('namespace Database\Factories\Nested;', $contents);
        $this->assertStringContainsString('final class OrderFactory extends Factory', $contents);
    }

    #[Test]
    public function it_reports_unknown_classes_in_recursive_mode(): void
    {
        $this->artisan('generate:factory', ['model' => ['Foo\\Bar'], '--recursive' => true])
            ->expectsOutputToContain('Unable to find Foo\Bar class!')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_skips_classes_that_are_not_eloquent_models(): void
    {
        $this->artisan('generate:factory', ['model' => [NotAModel::class]])->assertExitCode(0);

        $this->assertFileDoesNotExist($this->app->databasePath('factories/NotAModelFactory.php'));
    }

    #[Test]
    public function it_skips_abstract_model_classes(): void
    {
        $this->artisan('generate:factory', ['model' => [AbstractVehicle::class]])->assertExitCode(0);

        $this->assertFileDoesNotExist($this->app->databasePath('factories/AbstractVehicleFactory.php'));
    }

    #[Test]
    public function it_reports_models_that_fail_during_analysis(): void
    {
        $this->artisan('generate:factory', ['model' => [BrokenModel::class]])
            ->expectsOutputToContain('Could not analyze class ' . BrokenModel::class)
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($this->app->databasePath('factories/BrokenModelFactory.php'));
    }

    #[Test]
    public function it_invokes_factory_generator_hooks_when_present(): void
    {
        $model = new HookedModel();

        $this->app->instance(HookedModel::class, $model);

        $this->artisan('generate:factory', ['model' => [HookedModel::class]])->assertExitCode(0);

        $this->assertTrue($model->initialized);
        $this->assertTrue($model->finished);
        $this->assertFileExists($this->app->databasePath('factories/HookedModelFactory.php'));
    }

    #[Test]
    public function it_handles_database_qualified_table_names_without_columns(): void
    {
        $this->artisan('generate:factory', ['model' => [PrefixedModel::class]])->assertExitCode(0);

        $path = $this->app->databasePath('factories/PrefixedModelFactory.php');

        $this->assertFileExists($path);
        $this->assertStringNotContainsString("'name' =>", file_get_contents($path));
    }

    #[Test]
    public function it_reports_directories_that_cannot_be_created_recursively(): void
    {
        $command = $this->app->make(GenerateFactoryCommand::class);
        $command->setLaravel($this->app);

        foreach (['dir' => 'app/Models', 'namespace' => null, 'recursive' => true, 'force' => false] as $property => $value) {
            $reflection = new \ReflectionProperty($command, $property);
            $reflection->setValue($command, $value);
        }

        $output = new BufferedOutput();
        $command->setOutput(new \Illuminate\Console\OutputStyle(new ArrayInput([]), $output));

        File::deleteDirectory($this->app->databasePath('factories'));
        file_put_contents($this->app->databasePath('factories'), 'not a directory');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): void {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            (new \ReflectionMethod($command, 'makeDirRecursively'))->invoke($command, Order::class);
        } finally {
            restore_error_handler();
            unlink($this->app->databasePath('factories'));
        }

        $this->assertStringContainsString('Could not analyze class ' . Order::class, $output->fetch());
    }

    #[Test]
    public function it_reports_factories_that_cannot_be_saved(): void
    {
        $files = Mockery::mock(Filesystem::class)->makePartial();
        $files->shouldReceive('exists')->andReturn(false);
        $files->shouldReceive('put')->andReturn(false);

        $command = new GenerateFactoryCommand($files, $this->app->make('view'));
        $command->setLaravel($this->app);

        $output = new BufferedOutput();
        $exitCode = $command->run(new ArrayInput(['model' => [Customer::class]]), $output);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Failed to save model factory:', $output->fetch());
    }
}
