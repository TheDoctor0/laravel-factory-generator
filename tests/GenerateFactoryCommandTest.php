<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures\Customer;
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

    protected function factoryContents(string $filename): string
    {
        $factory = collect(File::allFiles($this->app->databasePath('factories')))
            ->first(fn ($f) => str_ends_with($f->getFilename(), $filename));

        $this->assertNotNull($factory, "No $filename was generated");

        return file_get_contents($factory->getPathname());
    }
}
