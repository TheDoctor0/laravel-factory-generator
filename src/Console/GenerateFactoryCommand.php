<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Console;

use Exception;
use SplFIleInfo;
use SplFileObject;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Str;
use Doctrine\DBAL\Types\Type;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Database\Eloquent\Relations\Relation;
use TheDoctor0\LaravelFactoryGenerator\Types\EnumType;
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
    protected $description = 'Generate test factories for models';

    protected string $dir;

    protected ?string $namespace;

    protected bool $force;

    protected bool $recursive;

    protected array $properties = [];

    public function __construct(public Filesystem $files, public Factory $view)
    {
        parent::__construct();
    }

    protected function getArguments(): array
    {
        return [
            ['model', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Which models to include', []],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['dir', 'D', InputOption::VALUE_OPTIONAL, 'The model directory'],
            ['force', 'F', InputOption::VALUE_NONE, 'Overwrite any existing model factory'],
            ['namespace', 'N', InputOption::VALUE_OPTIONAL, 'Model Namespace'],
            ['recursive', 'R', InputOption::VALUE_NONE, 'Generate model factory recursively']
        ];
    }

    public function handle(): void
    {
        $this->dir = $this->option('dir') ?? $this->defaultModelsDir();
        $this->namespace = $this->option('namespace');
        $this->force = $this->option('force');
        $this->recursive = $this->option('recursive');

        $models = $this->loadModels($this->argument('model'));

        foreach ($models as $model) {
            $class = class_basename($model);
            $filename = database_path("factories/{$class}Factory.php");

            $class = $this->generateClassName($model);

            if ($this->recursive) {
                $filename = $this->generateRecursiveFileName($class);
                $this->makeDirRecursively($class);
            }

            if (! $this->force && $this->files->exists($filename)) {
                $this->warn("Model factory exists, use --force to overwrite: $filename");

                continue;
            }

            $content = $this->generateFactory($class);

            if (! $content) {
                continue;
            }

            if (! $this->files->put($filename, $content)) {
                $this->error("Failed to save model factory: $filename");
            } else {
                $this->info("Model factory created: $filename");
            }
        }
    }

    protected function generateFactory(string $model): ?string
    {
        if (! $this->existsClassOrTrait($model)) {
            $this->error("Unable to find $model class!");

            return null;
        }

        $this->properties = [];

        try {
            $reflection = new ReflectionClass($model);

            if (! $this->isInstantiableModelClass($reflection)) {
                return null;
            }

            $eloquentModel = $this->laravel->make($model);

            if (method_exists($eloquentModel, 'factoryGeneratorInit')) {
                $eloquentModel->factoryGeneratorInit();
            }

            $this->getPropertiesFromTable($eloquentModel);
            $this->getPropertiesFromMethods($eloquentModel);

            if (method_exists($eloquentModel, 'factoryGeneratorEnd')) {
                $eloquentModel->factoryGeneratorEnd();
            }

            return "<?php\n\n{$this->createFactory($reflection)}";
        } catch (Exception $e) {
            $this->error("Could not analyze class $model.\nException: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * @noinspection PhpUndefinedMethodInspection
     */
    protected function loadModels(array $models = []): array
    {
        $rootDirectory = basename($this->laravel->path());

        if (! empty($models)) {
            return array_map(function ($name) use ($rootDirectory) {
                if (Str::contains($name, '\\')) {
                    return $name;
                }

                return str_replace(
                    ['/', DIRECTORY_SEPARATOR, "$rootDirectory\\"],
                    ['\\', '\\', $this->laravel->getNamespace()],
                    $this->formatPath($this->dir, $name)
                );
            }, $models);
        }

        if (! file_exists($this->laravel->basePath($this->dir))) {
            $this->error('Model directory does not exists.');

            return [];
        }

        return array_map(function (SplFIleInfo $file) use ($rootDirectory) {
            return str_replace(
                ['/', DIRECTORY_SEPARATOR, "$rootDirectory\\"],
                ['\\', '\\', $this->laravel->getNamespace()],
                $this->formatPath($file->getPath(), basename($file->getFilename(), '.php'))
            );
        }, $this->files->allFiles($this->dir));
    }

    protected function getPropertiesFromTable(Model $model): void
    {
        $table = $model->getConnection()->getTablePrefix() . $model->getTable();
        $database = null;

        if (Str::contains($table, '.')) {
            [$database, $table] = explode('.', $table);
        }

        $schema = Schema::connection($database);
        $columns = $schema->getColumns($table);

        if (! $columns) {
            return;
        }

        foreach ($columns as $column) {
            $field = $column['name'];
            $nullable = $column['nullable'];
            $type = $column['type_name'];

            if ($this->isFieldFakeable($field, $model)) {
                $this->setProperty($model, $field, $type, $nullable);
            }
        }
    }

    /**
     * @throws \ReflectionException
     */
    protected function getPropertiesFromMethods(Model $model): void
    {
        $methods = get_class_methods($model);

        foreach ($methods as $method) {
            if (Str::startsWith($method, 'get') || method_exists(Model::class, $method)) {
                continue;
            }

            $reflection = new ReflectionMethod($model, $method);

            $file = new SplFileObject($reflection->getFileName());
            $file->seek($reflection->getStartLine() - 1);

            $code = '';

            while ($file->key() < $reflection->getEndLine()) {
                $code .= $file->current();
                $file->next();
            }

            $code = trim(preg_replace('/\s\s+/', '', $code));
            $start = strpos($code, 'function ');
            $code = substr($code, $start, strrpos($code, '}') - $start + 1);

            $search = '$this->belongsTo(';

            if (! Str::contains($code, $search)) {
                continue;
            }

            /** @var \Illuminate\Database\Eloquent\Relations\BelongsTo $relationObject */
            $relationObject = $model->$method();

            if ($relationObject instanceof Relation) {
                $this->properties[$relationObject->getForeignKeyName()] = $this->factoryClass($relationObject);
            }
        }
    }

    protected function setProperty(Model $model, string $field, string $type, bool $nullable = false): void
    {
        if ($enumValues = EnumValues::get($model, $field)) {
            $enumValues = implode("', '", $enumValues);

            $this->properties[$field] = $this->fakerPrefix("randomElement(['$enumValues'])", $nullable);

            return;
        }

        if ($property = $this->mapByName($field, $nullable)) {
            $this->properties[$field] = $property;

            return;
        }

        if ($property = $this->mapByType($type, $nullable)) {
            $this->properties[$field] = $property;

            return;
        }

        $this->properties[$field] = $this->mapByType('string', $nullable);
    }

    protected function createFactory(ReflectionClass $reflection): string
    {
        return $this->view->make('factory-generator::factory', [
            'properties' => $this->properties,
            'name' => $reflection->getName(),
            'shortName' => $reflection->getShortName(),
            'namespace' => 'Database\\Factories' . $this->generateAdditionalNameSpace($reflection->getName()),
        ])->render();
    }

    protected function defaultModelsDir(): string
    {
        return $this->formatPath('app', 'Models');
    }

    protected function factoryClass(Relation $relation): string
    {
        $class = get_class($relation->getRelated());

        return "\\$class::factory()";
    }

    protected function fakerPrefix(string $type, bool $nullable = false): string
    {
        return !$nullable ? "fake()->$type" : "fake()->optional()->$type";
    }

    protected function isFieldFakeable(string $field, Model $model): bool
    {
        return ! ($model->incrementing && $field === $model->getKeyName())
            && ! ($model->timestamps && ($field === $model::CREATED_AT || $field === $model::UPDATED_AT))
            && ! (method_exists($model, 'getDeletedAtColumn') && $field === $model->getDeletedAtColumn());
    }

    protected function mapByName(string $field, bool $nullable = false): ?string
    {
        $fakeableNames = [
            'language' => $this->fakerPrefix('languageCode', $nullable),
            'lang' => $this->fakerPrefix('languageCode', $nullable),
            'locale' => $this->fakerPrefix('locale', $nullable),
            'city' => $this->fakerPrefix('city', $nullable),
            'town' => $this->fakerPrefix('city', $nullable),
            'town_city' => $this->fakerPrefix('city', $nullable),
            'state' => $this->fakerPrefix('state', $nullable),
            'region' => $this->fakerPrefix('state', $nullable),
            'region_state' => $this->fakerPrefix('state', $nullable),
            'company' => $this->fakerPrefix('company', $nullable),
            'country' => $this->fakerPrefix('country', $nullable),
            'description' => $this->fakerPrefix('text', $nullable),
            'email' => $this->fakerPrefix('safeEmail', $nullable),
            'email_address' => $this->fakerPrefix('safeEmail', $nullable),
            'first_name' => $this->fakerPrefix('firstName', $nullable),
            'firstname' => $this->fakerPrefix('firstName', $nullable),
            'last_name' => $this->fakerPrefix('lastName', $nullable),
            'lastname' => $this->fakerPrefix('lastName', $nullable),
            'name' => $this->fakerPrefix('name', $nullable),
            'full_name' => $this->fakerPrefix('name', $nullable),
            'lat' => $this->fakerPrefix('latitude', $nullable),
            'latitude' => $this->fakerPrefix('latitude', $nullable),
            'lng' => $this->fakerPrefix('longitude', $nullable),
            'longitude' => $this->fakerPrefix('longitude', $nullable),
            'password' => "bcrypt({$this->fakerPrefix('password', $nullable)})",
            'phone' => $this->fakerPrefix('phoneNumber', $nullable),
            'telephone' => $this->fakerPrefix('phoneNumber', $nullable),
            'phone_number' => $this->fakerPrefix('phoneNumber', $nullable),
            'postcode' => $this->fakerPrefix('postcode', $nullable),
            'postal_code' => $this->fakerPrefix('postcode', $nullable),
            'zip' => $this->fakerPrefix('postcode', $nullable),
            'zip_postal_code' => $this->fakerPrefix('postcode', $nullable),
            'slug' => $this->fakerPrefix('slug', $nullable),
            'street' => $this->fakerPrefix('streetName', $nullable),
            'address' => $this->fakerPrefix('address', $nullable),
            'address1' => $this->fakerPrefix('streetAddress', $nullable),
            'address2' => $this->fakerPrefix('secondaryAddress', $nullable),
            'summary' => $this->fakerPrefix('text', $nullable),
            'title' => $this->fakerPrefix('title', $nullable),
            'subject' => $this->fakerPrefix('title', $nullable),
            'note' => $this->fakerPrefix('sentence', $nullable),
            'sentence' => $this->fakerPrefix('sentence', $nullable),
            'url' => $this->fakerPrefix('url', $nullable),
            'link' => $this->fakerPrefix('url', $nullable),
            'href' => $this->fakerPrefix('url', $nullable),
            'domain' => $this->fakerPrefix('domainName', $nullable),
            'user_name' => $this->fakerPrefix('userName', $nullable),
            'username' => $this->fakerPrefix('userName', $nullable),
            'currency' => $this->fakerPrefix('currencyCode', $nullable),
            'guid' => $this->fakerPrefix('uuid', $nullable),
            'uuid' => $this->fakerPrefix('uuid', $nullable),
            'iban' => $this->fakerPrefix('iban(, $nullable)', $nullable),
            'mac' => $this->fakerPrefix('macAddress', $nullable),
            'ip' => $this->fakerPrefix('ipv4', $nullable),
            'ipv4' => $this->fakerPrefix('ipv4', $nullable),
            'ipv6' => $this->fakerPrefix('ipv6', $nullable),
            'request_ip' => $this->fakerPrefix('ipv4', $nullable),
            'user_agent' => $this->fakerPrefix('userAgent', $nullable),
            'request_user_agent' => $this->fakerPrefix('userAgent', $nullable),
            'iso3' => $this->fakerPrefix('countryISOAlpha3', $nullable),
            'hash' => $this->fakerPrefix('sha256', $nullable),
            'sha256' => $this->fakerPrefix('sha256', $nullable),
            'sha256_hash' => $this->fakerPrefix('sha256', $nullable),
            'sha1' => $this->fakerPrefix('sha1', $nullable),
            'sha1_hash' => $this->fakerPrefix('sha1', $nullable),
            'md5' => $this->fakerPrefix('md5', $nullable),
            'md5_hash' => $this->fakerPrefix('md5', $nullable),
            'remember_token' => 'Str::random(10)',
        ];

        return $fakeableNames[$field] ?? null;
    }

    protected function mapByType(string $field, bool $nullable = false): ?string
    {
        $fakeableTypes = [
            'string' => $this->fakerPrefix('word', $nullable),
            'text' => $this->fakerPrefix('text', $nullable),
            'date' => $this->fakerPrefix('date()', $nullable),
            'time' => $this->fakerPrefix('time()', $nullable),
            'guid' => $this->fakerPrefix('uuid', $nullable),
            'datetimetz' => $this->fakerPrefix('dateTime()', $nullable),
            'datetime' => $this->fakerPrefix('dateTime()', $nullable),
            'integer' => $this->fakerPrefix('randomNumber()', $nullable),
            'bigint' => $this->fakerPrefix('randomNumber()', $nullable),
            'smallint' => $this->fakerPrefix('randomNumber()', $nullable),
            'tinyint' => $this->fakerPrefix('randomNumber(1)', $nullable),
            'decimal' => $this->fakerPrefix('randomFloat()', $nullable),
            'float' => $this->fakerPrefix('randomFloat()', $nullable),
            'boolean' => $this->fakerPrefix('boolean', $nullable),
        ];

        return $fakeableTypes[$field] ?? null;
    }

    protected function formatPath(string ...$paths): string
    {
        return implode(DIRECTORY_SEPARATOR, $paths);
    }

    protected function getFileStructureDiff(string $class): array
    {
        if ($this->namespace) {
            return array_diff(explode('\\', $class), explode('\\', $this->namespace));
        }

        return array_diff(explode('\\', $class), explode('/', ucfirst($this->dir)));
    }

    protected function generateRecursiveFileName(string $class): string
    {
        return 'database/factories/' . implode('/', $this->getFileStructureDiff($class)) . 'Factory.php';
    }

    protected function generateClassName(string $model): string
    {
        $filePathDiff = $this->getFileStructureDiff($model);

        return $this->namespace ? $this->namespace . '\\' . implode('\\', $filePathDiff) : $model;
    }

    protected function generateAdditionalNameSpace(string $class): string
    {
        $append = '';
        $filePathDiff = $this->getFileStructureDiff($class);

        array_pop($filePathDiff);

        if ($this->recursive && ! empty($filePathDiff)) {
            $append = '\\' . implode('\\', $filePathDiff);
        }

        return $append;
    }

    protected function makeDirRecursively(string $class, int $permission = 0755): void
    {
        if (! $this->existsClassOrTrait($class)) {
            return;
        }

        try {
            $reflection = new ReflectionClass($class);
            $dir = 'database/factories' . str_replace('\\', '/', $this->generateAdditionalNameSpace($class));

            if (! file_exists($dir) && $this->isInstantiableModelClass($reflection)) {
                mkdir($dir, $permission, true);
            }
        } catch (Exception $e) {
            $this->error("Could not analyze class $class.\nException: {$e->getMessage()}");
        }
    }

    protected function isInstantiableModelClass(ReflectionClass $reflection): bool
    {
        return $reflection->isSubclassOf(Model::class) && $reflection->IsInstantiable();
    }

    protected function existsClassOrTrait(string $class): bool
    {
        return class_exists($class) || trait_exists($class);
    }
}
