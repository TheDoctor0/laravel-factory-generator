# Laravel Factory Generator

[![Packagist](https://img.shields.io/packagist/v/TheDoctor0/laravel-factory-generator.svg)](https://packagist.org/packages/TheDoctor0/laravel-factory-generator)
[![Packagist](https://img.shields.io/packagist/dt/TheDoctor0/laravel-factory-generator.svg)](https://packagist.org/packages/TheDoctor0/laravel-factory-generator)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/TheDoctor0/laravel-factory-generator/blob/master/LICENSE.md)

[![Banner](https://banners.beyondco.de/Laravel%20Factory%20Generator.png?theme=light&packageManager=composer+require&packageName=thedoctor0%2Flaravel-factory-generator+--dev&pattern=architect&style=style_1&description=Automatically+generate+test+factories+for+all+your+models&md=1&showWatermark=1&fontSize=100px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg)]()

Automatically generate [factories](https://laravel.com/docs/master/database-testing#writing-factories) from your existing models.

It will allow you to write tests containing your models much faster.

## Installation

You can install the package via composer:

```bash
composer require thedoctor0/laravel-factory-generator:1.3.2 --dev
```

## Usage

To generate all factories at once, simply run this artisan command:

```bash
php artisan generate:factory
```

It will find all models and generate test factories based on the database structure and model relations.

### Example

#### Migration and Model
```php
Schema::create('users', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name');
    $table->string('username');
    $table->string('email')->unique();
    $table->string('password', 60);
    $table->integer('company_id');
    $table->rememberToken();
    $table->timestamps();
});

class User extends Model {
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
```

#### Generated Factory

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\User>
 */
final class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'name' => faker()->name,
            'username' => faker()->userName,
            'email' => faker()->safeEmail,
            'password' => bcrypt(faker()->password),
            'company_id' => \App\Company::factory(),
            'remember_token' => Str::random(10),
        ];
    }
}
```

## Advanced usage

### Selecting models

To generate a factory for only specific model or models, run the artisan command:

```bash
php artisan generate:factory User Company
```

### Overwriting existing factories

By default, generation will not overwrite any existing model factories.

You can _force_ overwriting existing model factories by using the `--force` option:

```bash
php artisan generate:factory --force
```

### Customizing the output directory

By default, it will search recursively for models under the `app/Models` directory.

If your models are within a different folder, you can specify this using `--dir` option.

In this case, run the artisan command:

```bash
php artisan generate:factory --dir app/Models
```

### Customizing the namespace

If your models are within a different namespace, you can specify it using `--namespace` option.

You just need to execute this artisan command:

```bash
php artisan generate:factory --dir vendor/package/src/Models --namespace CustomNamespace\\Models
```

### Using recursive mode

By default, your model directory structure is not taken into account, even though it has subdirectories.

You can reflect it to `database/factories` directory by using the `--recursive` option:

```bash
php artisan generate:factory --recursive
```

### Customizing the factory template

If you want you can customize the factory template to suit your needs.

To publish a factory template to `resources/views/vendor/factory-generator/factory.blade.php`, run the artisan command:

```bash
php artisan vendor:publish --tag="factory-generator"
```

## License

The MIT License (MIT). Please see [license file](LICENSE.md) for more information.
