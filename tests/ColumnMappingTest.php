<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Tests;

use ReflectionMethod;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TheDoctor0\LaravelFactoryGenerator\Console\GenerateFactoryCommand;
use TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures\Post;

class ColumnMappingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('body');
            $table->text('content');
            $table->date('published_on');
            $table->dateTime('published_at');
            $table->time('starts');
            $table->boolean('is_active');
            $table->integer('views');
            $table->float('rating');
            $table->decimal('price', 6, 2);
            $table->timestamps();
            $table->softDeletes();
        });

        File::deleteDirectory($this->app->databasePath('factories'));
    }

    public static function fakeableNames(): array
    {
        return [
            ['language', 'fake()->languageCode'],
            ['lang', 'fake()->languageCode'],
            ['locale', 'fake()->locale'],
            ['city', 'fake()->city'],
            ['town', 'fake()->city'],
            ['town_city', 'fake()->city'],
            ['state', 'fake()->state'],
            ['region', 'fake()->state'],
            ['region_state', 'fake()->state'],
            ['company', 'fake()->company'],
            ['country', 'fake()->country'],
            ['description', 'fake()->text'],
            ['email', 'fake()->safeEmail'],
            ['email_address', 'fake()->safeEmail'],
            ['first_name', 'fake()->firstName'],
            ['firstname', 'fake()->firstName'],
            ['last_name', 'fake()->lastName'],
            ['lastname', 'fake()->lastName'],
            ['name', 'fake()->name'],
            ['full_name', 'fake()->name'],
            ['lat', 'fake()->latitude'],
            ['latitude', 'fake()->latitude'],
            ['lng', 'fake()->longitude'],
            ['longitude', 'fake()->longitude'],
            ['password', 'bcrypt(fake()->password)'],
            ['phone', 'fake()->phoneNumber'],
            ['telephone', 'fake()->phoneNumber'],
            ['phone_number', 'fake()->phoneNumber'],
            ['postcode', 'fake()->postcode'],
            ['postal_code', 'fake()->postcode'],
            ['zip', 'fake()->postcode'],
            ['zip_postal_code', 'fake()->postcode'],
            ['slug', 'fake()->slug'],
            ['street', 'fake()->streetName'],
            ['address', 'fake()->address'],
            ['address1', 'fake()->streetAddress'],
            ['address2', 'fake()->secondaryAddress'],
            ['summary', 'fake()->text'],
            ['title', 'fake()->sentence(4)'],
            ['subject', 'fake()->sentence(4)'],
            ['note', 'fake()->sentence'],
            ['sentence', 'fake()->sentence'],
            ['url', 'fake()->url'],
            ['link', 'fake()->url'],
            ['href', 'fake()->url'],
            ['domain', 'fake()->domainName'],
            ['user_name', 'fake()->userName'],
            ['username', 'fake()->userName'],
            ['currency', 'fake()->currencyCode'],
            ['guid', 'fake()->uuid'],
            ['uuid', 'fake()->uuid'],
            ['iban', 'fake()->iban()'],
            ['mac', 'fake()->macAddress'],
            ['ip', 'fake()->ipv4'],
            ['ipv4', 'fake()->ipv4'],
            ['ipv6', 'fake()->ipv6'],
            ['request_ip', 'fake()->ipv4'],
            ['user_agent', 'fake()->userAgent'],
            ['request_user_agent', 'fake()->userAgent'],
            ['iso3', 'fake()->countryISOAlpha3'],
            ['hash', 'fake()->sha256'],
            ['sha256', 'fake()->sha256'],
            ['sha256_hash', 'fake()->sha256'],
            ['sha1', 'fake()->sha1'],
            ['sha1_hash', 'fake()->sha1'],
            ['md5', 'fake()->md5'],
            ['md5_hash', 'fake()->md5'],
            ['remember_token', 'Str::random(10)'],
        ];
    }

    public static function fakeableTypes(): array
    {
        return [
            ['string', 'fake()->word'],
            ['text', 'fake()->text'],
            ['date', 'fake()->date()'],
            ['time', 'fake()->time()'],
            ['timestamp', 'fake()->dateTime()'],
            ['guid', 'fake()->uuid'],
            ['datetimetz', 'fake()->dateTime()'],
            ['datetime', 'fake()->dateTime()'],
            ['integer', 'fake()->randomNumber()'],
            ['int', 'fake()->randomNumber()'],
            ['bigint', 'fake()->randomNumber()'],
            ['smallint', 'fake()->randomNumber()'],
            ['tinyint', 'fake()->randomNumber(1)'],
            ['float', 'fake()->randomFloat()'],
            ['boolean', 'fake()->boolean'],
        ];
    }

    #[Test]
    #[DataProvider('fakeableNames')]
    public function it_maps_special_field_names_to_faker_formatters(string $field, string $expected): void
    {
        $this->assertSame($expected, $this->invokeMap('mapByName', $field));
    }

    #[Test]
    #[DataProvider('fakeableTypes')]
    public function it_maps_column_types_to_faker_formatters(string $type, string $expected): void
    {
        $this->assertSame($expected, $this->invokeMap('mapByType', $type));
    }

    #[Test]
    public function it_wraps_nullable_mappings_with_optional(): void
    {
        $this->assertSame('fake()->optional()->city', $this->invokeMap('mapByName', 'city', true));
        $this->assertSame('fake()->optional()->boolean', $this->invokeMap('mapByType', 'boolean', true));
    }

    #[Test]
    public function it_returns_null_for_unknown_names_and_types(): void
    {
        $this->assertNull($this->invokeMap('mapByName', 'body'));
        $this->assertNull($this->invokeMap('mapByType', 'varchar'));
    }

    #[Test]
    public function it_maps_real_columns_and_falls_back_to_word_for_unhandled_types(): void
    {
        $this->artisan('generate:factory', ['model' => [Post::class]])->assertExitCode(0);

        $contents = file_get_contents($this->app->databasePath('factories/PostFactory.php'));

        $this->assertStringContainsString("'title' => fake()->sentence(4)", $contents);
        $this->assertStringContainsString("'body' => fake()->word", $contents);
        $this->assertStringContainsString("'content' => fake()->text", $contents);
        $this->assertStringContainsString("'published_on' => fake()->date()", $contents);
        $this->assertStringContainsString("'published_at' => fake()->dateTime()", $contents);
        $this->assertStringContainsString("'starts' => fake()->time()", $contents);
        $this->assertStringContainsString("'is_active' => fake()->randomNumber(1)", $contents);
        $this->assertStringContainsString("'views' => fake()->randomNumber()", $contents);
        $this->assertStringContainsString("'rating' => fake()->randomFloat()", $contents);
        $this->assertStringContainsString("'price' => fake()->word", $contents);
        $this->assertStringNotContainsString("'id' =>", $contents);
        $this->assertStringNotContainsString("'created_at' =>", $contents);
        $this->assertStringNotContainsString("'updated_at' =>", $contents);
        $this->assertStringNotContainsString("'deleted_at' =>", $contents);
    }

    protected function invokeMap(string $method, string $value, bool $nullable = false): ?string
    {
        $command = $this->app->make(GenerateFactoryCommand::class);

        return (new ReflectionMethod($command, $method))->invoke($command, $value, $nullable);
    }
}
