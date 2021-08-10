<?php

namespace Shift\FactoryGenerator;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Naoray\EloquentModelAnalyzer\Analyzer;
use Naoray\EloquentModelAnalyzer\Column;
use Naoray\EloquentModelAnalyzer\RelationMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

class FactoryGenerator
{
    /**
     * @var \Shift\FactoryGenerator\TypeGuesser
     */
    protected $typeGuesser;

    private $includeNullableColumns = false;

    private $overwrite = false;

    /**
     * Instance of the model the factory is created for.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $modelInstance;

    public function __construct(TypeGuesser $guesser, $nullables, $overwrite)
    {
        $this->typeGuesser = $guesser;
        $this->includeNullableColumns = $nullables;
        $this->overwrite = $overwrite;
    }

    public function generate($model)
    {
        if (!$modelClass = $this->modelExists($model)) {
            return null;
        }

        $factoryPath = $this->factoryPath($modelClass);

        if (!$this->overwrite && $this->factoryExists($factoryPath)) {
            return null;
        }

        $this->modelInstance = new $modelClass();

        Analyzer::columns($this->modelInstance)
            ->mapWithKeys(function (Column $column) {
                return $this->mapTableProperties($column);
            })
            ->merge($this->getPropertiesFromMethods())
            ->filter()
            ->unique()
            ->values()
            ->pipe(function ($properties) use ($factoryPath, $modelClass) {
                $this->writeFactoryFile($factoryPath, $properties, $modelClass);
                $this->addFactoryTrait($modelClass);
            });

        return $factoryPath;
    }

    private function factoryPath($model)
    {
        $subDirectory = Str::of($model)
            ->replaceFirst('App\\Models\\', '')
            ->replaceFirst('App\\', '');

        return database_path('factories/' . str_replace('\\', '/', $subDirectory) . 'Factory.php');
    }

    /**
     * Maps properties.
     *
     * @param Column $column
     * @return array
     */
    protected function mapTableProperties(Column $column): array
    {
        $key = $column->getName();

        if (!$this->shouldBeIncluded($column)) {
            return $this->mapToFactory($key);
        }

        if ($column->isForeignKey()) {
            return $this->mapToFactory(
                $key,
                $this->buildRelationFunction($key)
            );
        }

        if ($key === 'password') {
            return $this->mapToFactory($key, "Hash::make('password')");
        }

        $value = $column->isUnique()
            ? '$this->faker->unique()->'
            : '$this->faker->';

        return $this->mapToFactory($key, $value . $this->mapToFaker($column));
    }

    /**
     * Checks if a given column should be included in the factory.
     *
     * @param Column $column
     */
    protected function shouldBeIncluded(Column $column)
    {
        $shouldBeIncluded = ($column->getNotNull() || $this->includeNullableColumns)
            && !$column->getAutoincrement();

        if (!$this->modelInstance->usesTimestamps()) {
            return $shouldBeIncluded;
        }

        $timestamps = [
            $this->modelInstance->getCreatedAtColumn(),
            $this->modelInstance->getUpdatedAtColumn(),
        ];

        if (method_exists($this->modelInstance, 'getDeletedAtColumn')) {
            $timestamps[] = $this->modelInstance->getDeletedAtColumn();
        }

        return $shouldBeIncluded
            && !in_array($column->getName(), $timestamps);
    }

    protected function mapToFactory($key, $value = null): array
    {
        return [
            $key => is_null($value) ? $value : "'{$key}' => $value",
        ];
    }

    /**
     * Get properties via reflection from methods.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getPropertiesFromMethods()
    {
        return Analyzer::relations($this->modelInstance)
            ->filter(function (RelationMethod $method) {
                return $method->returnType() === BelongsTo::class;
            })
            ->mapWithKeys(function (RelationMethod $method) {
                $property = $method->foreignKey();

                return [$property => "'$property' => " . $this->buildRelationFunction($property, $method)];
            });
    }

    /**
     * Check if the given model exists.
     *
     * @param string $name
     *
     * @return bool|string
     */
    protected function modelExists($name)
    {
        if (class_exists($modelClass = $this->qualifyClass($name))) {
            return $modelClass;
        }

        // TODO: this check should happen before calling this service class...
        throw new \UnexpectedValueException('could not find model [' . $name . ']');
    }

    /**
     * Check if factory already exists.
     *
     * @param string $name
     *
     * @return bool|string
     */
    protected function factoryExists($path)
    {
        return File::exists($path);
    }

    /**
     * Map name to faker method.
     *
     * @param Column $column
     *
     * @return string
     */
    protected function mapToFaker(Column $column)
    {
        return $this->typeGuesser->guess(
            $column->getName(),
            $column->getType(),
            $column->getLength()
        );
    }

    /**
     * Build relation function.
     *
     * @param string $column
     *
     * @return string
     */
    public function buildRelationFunction(string $column, $relationMethod = null)
    {
        $relationName = optional($relationMethod)->getName() ?? Str::camel(str_replace('_id', '', $column));
        $foreignCallback = '\\App\\REPLACE_THIS::factory()';

        try {
            $relatedModel = get_class($this->modelInstance->$relationName()->getRelated());

            return str_replace('App\\REPLACE_THIS', $relatedModel, $foreignCallback);
        } catch (\Exception $e) {
            return $foreignCallback;
        }
    }

    /**
     * Parse the class name and format according to the root namespace.
     *
     * @param string $name
     *
     * @return string
     */
    protected function qualifyClass($name)
    {
        $name = ltrim($name, '\\/');

        $rootNamespace = app()->getNamespace();

        if (Str::startsWith($name, $rootNamespace)) {
            return $name;
        }

        $name = str_replace('/', '\\', $name);

        return $this->qualifyClass(
            trim($rootNamespace, '\\') . '\\' . $name
        );
    }

    /**
     * Writes data to factory file.
     *
     * @param string $path
     * @param array $data
     *
     * @return bool
     */
    protected function writeFactoryFile($path, $data, $modelClass)
    {
        File::ensureDirectoryExists(dirname($path));

        $definition = '';
        foreach ($data as $value) {
            $definition .= PHP_EOL . '            ' . $value . ',';
        }

        $factoryQualifiedName = \Illuminate\Database\Eloquent\Factories\Factory::resolveFactoryName($modelClass);
        $factoryNamespace = Str::beforeLast($factoryQualifiedName, '\\');
        $contents = File::get(__DIR__ . '/../stubs/factory.stub');
        $contents = str_replace('{{ factoryNamespace }}', $factoryNamespace, $contents);
        $contents = str_replace('{{ namespacedModel }}', $modelClass, $contents);
        $contents = str_replace('{{ model }}', class_basename($modelClass), $contents);
        $contents = str_replace('            //', trim($definition, PHP_EOL), $contents);

        File::put($path, $contents);
    }

    protected function addFactoryTrait($modelClass)
    {
        $traits = class_uses_recursive($modelClass);
        if (is_array($traits) && in_array('Illuminate\\Database\\Eloquent\\Factories\\HasFactory', $traits)) {
            return;
        }

        $path = (new \ReflectionClass($modelClass))->getFileName();

        $parser = (new ParserFactory)->create(ParserFactory::ONLY_PHP7);

        $contents = File::get($path);
        $lines = explode(PHP_EOL, $contents);
        $stmts = $parser->parse($contents);

        $nodeFinder = new NodeFinder;

        $class = $nodeFinder->findFirstInstanceOf($stmts, \PhpParser\Node\Stmt\Class_::class);

        $import = $nodeFinder->findFirstInstanceOf($stmts, \PhpParser\Node\Stmt\Use_::class);
        if (empty($import)) {
            $line = $class->getStartLine();
        } else {
            $line = $import->getStartLine();
        }

        array_splice($lines, $line - 1, 0, 'use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;');

        $traits = $class->getTraitUses();
        if (empty($traits)) {
            // TODO: refactor
            $line = $class->getStartLine() + 1; // add 1 due to import above
            $found = false;
            while (!$found) {
                $found = Str::contains($lines[$line], '{'); // found closing curly
                ++$line; // advance one more line to place the trait...
            }

            array_splice($lines, $line, 0, '    use HasFactory;' . PHP_EOL);
        } else {
            $line = $traits[0]->getStartLine();
            array_splice($lines, $line + 1, 0, '    use HasFactory;');
        }

        File::put($path, implode(PHP_EOL, $lines));
    }
}
