declare(strict_types=1);

namespace {{ $namespace }};

use {{ $name }};
@isset($properties['remember_token'])
use Illuminate\Support\Str;
@endisset
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @@extends Factory<\{{ $name }}>
 */
final class {{ $shortName }}Factory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = {{ $shortName }}::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    public function definition(): array
    {
        return [
@foreach($properties as $name => $property)
            '{{$name}}' => {!! $property !!},
@endforeach
        ];
    }
}
