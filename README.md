# Laravel Factory Generator

[![Packagist](https://img.shields.io/packagist/v/TheDoctor0/laravel-factory-generator.svg)](https://packagist.org/packages/TheDoctor0/laravel-factory-generator)
[![Packagist](https://img.shields.io/packagist/dt/TheDoctor0/laravel-factory-generator.svg)](https://packagist.org/packages/TheDoctor0/laravel-factory-generator)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/TheDoctor0/laravel-factory-generator/blob/master/LICENSE.md)

This package will generate [factories](https://laravel.com/docs/master/database-testing#writing-factories) from your existing models.
 
That way you can get started with testing your Laravel application more quickly!

It is a forked version of [mpociot/laravel-test-factory-helper](https://github.com/mpociot/laravel-test-factory-helper) package.

## Installation

You can install the package via composer:

```bash
composer require thedoctor0/laravel-factory-generator --dev
```

## Usage

To generate multiple factories at once, run the artisan command:

`php artisan generate:factory`

This command will find all models within your application and create test factories. 

To generate a factory for only specific model or models, run the artisan command:

`php artisan generate:factory User Company`

By default, generation will not overwrite any existing model factories. 

You can _force_ overwriting existing model factories by using the `--force` option:

`php artisan generate:factory --force`

By default, it will search recursively for models under the `app/Models` (Laravel 8.x) or `app` for (Laravel 6.x and 7.x).

If your models are within a different folder, you can specify this using `--dir` option. 

In this case, run the artisan command:

`php artisan generate:factory --dir app/Models`

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

For Laravel 6.x and 7.x:

```php
$factory->define(App\User::class, function (Faker\Generator $faker) {
    return [
        'name' => $faker->name,
        'username' => $faker->userName,
        'email' => $faker->safeEmail,
        'password' => bcrypt($faker->password),
        'company_id' => factory(App\Company::class),
        'remember_token' => Str::random(10),
    ];
});
```

For Laravel 8.x:
```php
class UserFactory extends Factory
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
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'username' => $this->faker->userName,
            'email' => $this->faker->safeEmail,
            'password' => bcrypt($this->faker->password),
            'company_id' => \App\Company::factory(),
            'remember_token' => Str::random(10),
        ];
    }
}
```

## License

The MIT License (MIT). Please see [license file](LICENSE.md) for more information.
