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
    * Indicates if the callbacks should be executed.
    *
    * @var bool $shouldExecuteCallbacks
    */
    protected static bool $shouldExecuteCallbacks = true;



    /**
    * Set the callbacks to not be executed.
    *
    * @return {{ $shortName }}Factory
    */
    public function withoutCallbacks(): {{ $shortName }}Factory
    {
        {{ $shortName }}Factory::$shouldExecuteCallbacks = false;

        return $this;
    }

    /**
    * Configure the model factory.
    *
    * @return $this
    */
    public function configure(): {{ $shortName }}Factory
    {
        if (!{{ $shortName }}Factory::$shouldExecuteCallbacks) {
        return $this;
        }

        return $this->afterMaking(function ({{ $shortName }} ${{ Str::lower($shortName) }}) {
            //
        })->afterCreating(function ({{ $shortName }} ${{ Str::lower($shortName) }}) {
            //
        });
    }

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
