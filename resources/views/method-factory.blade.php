<?php

declare(strict_types=1);

use Faker\Generator as Faker;

/* @var $factory \Illuminate\Database\Eloquent\Factory */
$factory->define({{ $reflection->getName() }}::class, function (Faker $faker) {
    return [
@foreach($properties as $name => $property)
        '{{$name}}' => {!! $property !!},
@endforeach
    ];
});
