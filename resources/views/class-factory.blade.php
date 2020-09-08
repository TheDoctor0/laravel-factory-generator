namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use {{ $reflection->name }};

class {{ class_basename($reflection->name) }}Factory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = {{ class_basename($reflection->name) }}::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    public function definition()
    {
        return [
@foreach($properties as $name => $property)
            '{{$name}}' => {!! $property !!},
@endforeach
        ];
    }
}
