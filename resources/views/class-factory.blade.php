namespace {{'Database\\Factories'. $append}};

use Illuminate\Database\Eloquent\Factories\Factory;
@isset($properties['remember_token'])
use Illuminate\Support\Str;
@endisset
use {{ $reflection->getName() }};

/**
 * @extends Factory<{{ $reflection->getShortName() }}>
 */
class {{ $reflection->getShortName() }}Factory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = {{ $reflection->getShortName() }}::class;

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
