declare(strict_types=1);

use Faker\Generator as Faker;
@isset($properties['remember_token'])
use Illuminate\Support\Str;
@endisset

/* @var $factory \Illuminate\Database\Eloquent\Factory */
$factory->define({{ $name }}::class, function (Faker $faker) {
    return [
@foreach($properties as $name => $property)
        '{{$name}}' => {!! $property !!},
@endforeach
    ];
});
