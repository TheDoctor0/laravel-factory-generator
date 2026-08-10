<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures\Customer;
use TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures\Order;
use TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures\Post;
use TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures\Ticket;

class GenerateFactoryCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('city')->nullable();
            $table->string('iban');
            $table->timestamps();
        });

        File::deleteDirectory($this->app->databasePath('factories'));
    }

    #[Test]
    public function it_generates_a_class_based_factory_from_a_model(): void
    {
        $this->artisan('generate:factory', ['model' => [Customer::class]])
            ->assertExitCode(0);

        $contents = $this->factoryContents('CustomerFactory.php');

        $this->assertStringContainsString('extends Factory', $contents);
        $this->assertStringContainsString("'name' => fake()->name", $contents);
        $this->assertStringContainsString("'email' => fake()->safeEmail", $contents);
        $this->assertStringContainsString('fake()->optional()', $contents);
        $this->assertStringContainsString('public function definition(): array', $contents);
    }

    #[Test]
    public function it_maps_enum_columns_to_random_element(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->enum('status', ['open', 'closed', 'pending']);
        });

        $this->artisan('generate:factory', ['model' => [Ticket::class]])
            ->assertExitCode(0);

        $contents = $this->factoryContents('TicketFactory.php');

        $this->assertStringContainsString(
            "'status' => fake()->randomElement(['open', 'closed', 'pending'])",
            $contents
        );
    }

    #[Test]
    public function it_prints_the_factory_without_writing_files_in_dry_run_mode(): void
    {
        $this->artisan('generate:factory', ['model' => [Customer::class], '--dry-run' => true])
            ->expectsOutputToContain('Model factory preview:')
            ->expectsOutputToContain('public function definition(): array')
            ->assertExitCode(0);

        $this->assertFalse(
            File::isDirectory($this->app->databasePath('factories')),
            'Dry run must not write any factory files'
        );
    }

    #[Test]
    public function it_maps_title_columns_to_a_non_deprecated_faker_method(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('subject')->nullable();
        });

        $this->artisan('generate:factory', ['model' => [Post::class]])
            ->assertExitCode(0);

        $contents = $this->factoryContents('PostFactory.php');

        $this->assertStringContainsString("'title' => fake()->sentence(4)", $contents);
        $this->assertStringContainsString("'subject' => fake()->optional()->sentence(4)", $contents);
        $this->assertStringNotContainsString('fake()->title', $contents);
    }

    #[Test]
    public function it_maps_belongs_to_relations_to_related_factories(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id');
            $table->string('number');
        });

        $this->artisan('generate:factory', ['model' => [Order::class]])
            ->assertExitCode(0);

        $contents = $this->factoryContents('OrderFactory.php');

        $this->assertStringContainsString(
            "'customer_id' => \\" . Customer::class . '::factory()',
            $contents
        );
    }

    #[Test]
    public function it_does_not_overwrite_an_existing_factory_without_force(): void
    {
        File::ensureDirectoryExists($this->app->databasePath('factories'));
        File::put($this->app->databasePath('factories/CustomerFactory.php'), 'original');

        $this->artisan('generate:factory', ['model' => [Customer::class]])
            ->expectsOutputToContain('Model factory exists, use --force to overwrite')
            ->assertExitCode(0);

        $this->assertSame(
            'original',
            File::get($this->app->databasePath('factories/CustomerFactory.php'))
        );

        $this->artisan('generate:factory', ['model' => [Customer::class], '--force' => true])
            ->assertExitCode(0);

        $this->assertStringContainsString(
            'extends Factory',
            File::get($this->app->databasePath('factories/CustomerFactory.php'))
        );
    }

    #[Test]
    public function it_generates_a_valid_iban_mapping(): void
    {
        $this->artisan('generate:factory', ['model' => [Customer::class]])
            ->assertExitCode(0);

        $contents = $this->factoryContents('CustomerFactory.php');

        $this->assertStringContainsString("'iban' => fake()->iban()", $contents);
        $this->assertStringNotContainsString('iban(, $nullable)', $contents);
        $this->assertNotFalse(token_get_all($contents, TOKEN_PARSE));
    }

    #[Test]
    public function it_maps_decimal_columns_even_when_precision_is_not_reported(): void
    {
        // e.g. PostgreSQL reports "numeric(8,2)" and some drivers report a bare
        // "decimal" - the precision regex then does not match.
        $this->assertSame(
            'fake()->randomFloat(2, 0, 999999)',
            $this->mapDecimalColumn('decimal')
        );

        $this->assertSame(
            'fake()->randomFloat(2, 0, 9999)',
            $this->mapDecimalColumn('decimal(6,2)')
        );
    }

    protected function mapDecimalColumn(string $fullType): string
    {
        $command = $this->app->make(\TheDoctor0\LaravelFactoryGenerator\Console\GenerateFactoryCommand::class);

        $setProperty = new \ReflectionMethod($command, 'setProperty');
        $setProperty->invoke($command, new Customer(), 'price', 'decimal', [
            'name' => 'price',
            'type' => $fullType,
            'type_name' => 'decimal',
            'nullable' => false,
        ], false);

        $properties = new \ReflectionProperty($command, 'properties');

        return $properties->getValue($command)['price'];
    }

    protected function factoryContents(string $filename): string
    {
        $factory = collect(File::allFiles($this->app->databasePath('factories')))
            ->first(fn ($f) => str_ends_with($f->getFilename(), $filename));

        $this->assertNotNull($factory, "No $filename was generated");

        return file_get_contents($factory->getPathname());
    }
}
