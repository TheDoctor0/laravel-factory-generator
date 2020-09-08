<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Console;

use Exception;
use Illuminate\Support\Str;
use Doctrine\DBAL\Types\Type;
use Illuminate\Console\Command;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Database\Eloquent\Relations\Relation;
use Symfony\Component\Console\Output\OutputInterface;
use TheDoctor0\LaravelFactoryGenerator\Database\EnumValues;

class GenerateFactoryCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'generate:factory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate database test factories for models';

    /**
     * @var string
     */
    protected $dir;

    /**
     * @var bool
     */
    protected $force;

    /**
     * @var \Illuminate\Contracts\Filesystem\Filesystem $files
     */
    protected $files;

    /**
     * @var \Illuminate\Contracts\View\Factory
     */
    protected $view;

    /**
     * @var string
     */
    protected $existingFactories = '';

    /**
     * @var array
     */
    protected $properties = [];

    /**
     * @param Filesystem $files
     * @param            $view
     */
    public function __construct(Filesystem $files, Factory $view)
    {
        parent::__construct();

        $this->files = $files;
        $this->view = $view;
    }

    /**
     * Execute the console command.
     *
     * @return void
     * @throws \Doctrine\DBAL\DBALException
     */
    public function handle(): void
    {
        Type::addType('customEnum', EnumType::class);
        $this->dir = $this->option('dir') ?? $this->defaultModelsDir();
        $this->force = $this->option('force');

        $models = $this->loadModels($this->argument('model'));

        foreach ($models as $model) {
            $filename = 'database/factories/' . class_basename($model) . 'Factory.php';

            if (! $this->force && $this->files->exists($filename)) {
                $this->warn('Model factory exists, use --force to overwrite: ' . $filename);

                continue;
            }

            $result = $this->generateFactory($model);

            if ($result === false) {
                continue;
            }

            $written = $this->files->put($filename, $result);
            if ($written !== false) {
                $this->info('Model factory created: ' . $filename);
            } else {
                $this->error('Failed to create model factory: ' . $filename);
            }
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments(): array
    {
        return [
            ['model', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Which models to include', []],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            ['dir', 'D', InputOption::VALUE_OPTIONAL, 'The model directory', $this->dir],
            ['force', 'F', InputOption::VALUE_NONE, 'Overwrite any existing model factory'],
        ];
    }

    protected function generateFactory($model)
    {
        $output = '<?php' . "\n\n";

        $this->properties = [];

        if (! class_exists($model)) {
            if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $this->error("Unable to find '$model' class");
            }

            return false;
        }

        try {
            // handle abstract classes, interfaces, ...
            $reflectionClass = new \ReflectionClass($model);

            if (! $reflectionClass->isSubclassOf(Model::class)) {
                return false;
            }

            if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $this->comment("Loading model '$model'");
            }

            if (! $reflectionClass->IsInstantiable()) {
                // ignore abstract class or interface
                return false;
            }

            $model = $this->laravel->make($model);

            $this->getPropertiesFromTable($model);
            $this->getPropertiesFromMethods($model);

            $output .= $this->createFactory($model);
        } catch (Exception $e) {
            $this->error("Exception: " . $e->getMessage() . "\nCould not analyze class $model.");
        }

        return $output;
    }

    protected function loadModels($models = [])
    {
        if (! empty($models)) {
            return array_map(function ($name) {
                if (strpos($name, '\\') !== false) {
                    return $name;
                }

                return str_replace(
                    [DIRECTORY_SEPARATOR, basename($this->laravel->path()) . '\\'],
                    ['\\', $this->laravel->getNamespace()],
                    $this->dir . DIRECTORY_SEPARATOR . $name
                );
            }, $models);
        }

        $dir = base_path($this->dir);
        if (! file_exists($dir)) {
            return [];
        }

        return array_map(function (\SplFIleInfo $file) {
            return str_replace(
                [DIRECTORY_SEPARATOR, basename($this->laravel->path()) . '\\'],
                ['\\', $this->laravel->getNamespace()],
                $file->getPath() . DIRECTORY_SEPARATOR . basename($file->getFilename(), '.php')
            );
        }, $this->files->allFiles($this->dir));
    }

    /**
     * Load the properties from the database table.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function getPropertiesFromTable(Model $model): void
    {
        $table = $model->getConnection()->getTablePrefix() . $model->getTable();
        $schema = $model->getConnection()->getDoctrineSchemaManager($table);
        $databasePlatform = $schema->getDatabasePlatform();
        $databasePlatform->registerDoctrineTypeMapping('enum', 'customEnum');

        $platformName = $databasePlatform->getName();
        $customTypes = $this->laravel['config']->get("ide-helper.custom_db_types.{$platformName}", []);
        foreach ($customTypes as $yourTypeName => $doctrineTypeName) {
            $databasePlatform->registerDoctrineTypeMapping($yourTypeName, $doctrineTypeName);
        }

        $database = null;
        if (strpos($table, '.')) {
            [$database, $table] = explode('.', $table);
        }

        $columns = $schema->listTableColumns($table, $database);

        if (! $columns) {
            return;
        }

        foreach ($columns as $column) {
            $field = $column->getName();

            if (in_array($field, $model->getDates(), true)) {
                $type = 'datetime';
            } else {
                $type = $column->getType()->getName();
            }
            if (! ($model->incrementing && $model->getKeyName() === $field) &&
                $field !== $model::CREATED_AT &&
                $field !== $model::UPDATED_AT
            ) {
                if (! method_exists($model, 'getDeletedAtColumn') || (method_exists($model,
                            'getDeletedAtColumn') && $field !== $model->getDeletedAtColumn())) {
                    $this->setProperty($model, $field, $type);
                }
            }
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @throws \ReflectionException
     */
    protected function getPropertiesFromMethods(Model $model): void
    {
        $methods = get_class_methods($model);

        foreach ($methods as $method) {
            if (! Str::startsWith($method, 'get') && ! method_exists(Model::class, $method)) {
                // Use reflection to inspect the code, based on Illuminate/Support/SerializableClosure.php
                $reflection = new \ReflectionMethod($model, $method);
                $file = new \SplFileObject($reflection->getFileName());
                $file->seek($reflection->getStartLine() - 1);
                $code = '';
                while ($file->key() < $reflection->getEndLine()) {
                    $code .= $file->current();
                    $file->next();
                }
                $code = trim(preg_replace('/\s\s+/', '', $code));
                $begin = strpos($code, 'function(');
                $code = substr($code, $begin, strrpos($code, '}') - $begin + 1);
                foreach (['belongsTo'] as $relation) {
                    $search = '$this->' . $relation . '(';
                    if ($pos = stripos($code, $search)) {
                        $relationObj = $model->$method();
                        if ($relationObj instanceof Relation) {
                            /** @var \Illuminate\Database\Eloquent\Relations\Relation $relationObj */
                            $this->setProperty($model, $relationObj->getForeignKeyName(),
                                'factory(' . get_class($relationObj->getRelated()) . '::class)');
                        }
                    }
                }
            }
        }
    }

    /**
     * @param             $model
     * @param string      $name
     * @param string|null $type
     */
    protected function setProperty(Model $model, string $name, $type = null): void
    {
        if ($type !== null && Str::startsWith($type, 'factory(')) {
            $this->properties[$name] = $type;

            return;
        }

        if ($enumValues = EnumValues::get($model, $name)) {
            $this->properties[$name] = '$faker->randomElement([\'' . implode("', '", $enumValues) . '\'])';

            return;
        }

        $fakeableNames = [
            'city' => '$faker->city',
            'company' => '$faker->company',
            'country' => '$faker->country',
            'description' => '$faker->text',
            'email' => '$faker->safeEmail',
            'first_name' => '$faker->firstName',
            'firstname' => '$faker->firstName',
            'guid' => '$faker->uuid',
            'last_name' => '$faker->lastName',
            'lastname' => '$faker->lastName',
            'lat' => '$faker->latitude',
            'latitude' => '$faker->latitude',
            'lng' => '$faker->longitude',
            'longitude' => '$faker->longitude',
            'name' => '$faker->name',
            'password' => 'bcrypt($faker->password)',
            'phone' => '$faker->phoneNumber',
            'phone_number' => '$faker->phoneNumber',
            'postcode' => '$faker->postcode',
            'postal_code' => '$faker->postcode',
            'remember_token' => '$faker->regexify(\'[A-Za-z0-9]{10}\')',
            'slug' => '$faker->slug',
            'street' => '$faker->streetName',
            'address1' => '$faker->streetAddress',
            'address2' => '$faker->secondaryAddress',
            'summary' => '$faker->text',
            'url' => '$faker->url',
            'user_name' => '$faker->userName',
            'username' => '$faker->userName',
            'uuid' => '$faker->uuid',
            'zip' => '$faker->postcode',
        ];

        if (isset($fakeableNames[$name])) {
            $this->properties[$name] = $fakeableNames[$name];

            return;
        }

        $fakeableTypes = [
            'string' => '$faker->word',
            'text' => '$faker->text',
            'date' => '$faker->date()',
            'time' => '$faker->time()',
            'guid' => '$faker->word',
            'datetimetz' => '$faker->dateTime()',
            'datetime' => '$faker->dateTime()',
            'integer' => '$faker->randomNumber()',
            'bigint' => '$faker->randomNumber()',
            'smallint' => '$faker->randomNumber()',
            'decimal' => '$faker->randomFloat()',
            'float' => '$faker->randomFloat()',
            'boolean' => '$faker->boolean',
        ];

        if (isset($fakeableTypes[$type])) {
            $this->properties[$name] = $fakeableTypes[$type];

            return;
        }

        $this->properties[$name] = '$faker->word';
    }

    /**
     * @param string $class
     *
     * @return string
     * @throws \ReflectionException
     */
    protected function createFactory(string $class): string
    {
        $reflection = new \ReflectionClass($class);

        return $this->view->make('factory-generator::factory', [
            'reflection' => $reflection,
            'properties' => $this->properties,
        ])->render();
    }

    /**
     * Return default models directory.
     *
     * @return string
     */
    protected function defaultModelsDir(): string
    {
        return $this->isLaravel8OrAbove()
            ? 'app' . DIRECTORY_SEPARATOR . 'Models'
            : 'app';
    }

    /**
     * Return if running on Laravel 8+.
     *
     * @return bool
     */
    protected function isLaravel8OrAbove(): bool
    {
        return (int) $this->laravel->version()[0] >= 8;
    }
}
