<?php

namespace Shift\FactoryGenerator;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Naoray\EloquentModelAnalyzer\Analyzer;
use Naoray\EloquentModelAnalyzer\Column;
use Naoray\EloquentModelAnalyzer\RelationMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

class FactoryGenerator
{
    private bool $includeNullableColumns = false;

    protected Model $modelInstance;

    private bool $overwrite = false;

    protected TypeGuesser $typeGuesser;

    public function __construct(TypeGuesser $guesser, bool $nullables, bool $overwrite)
    {
        $this->typeGuesser = $guesser;
        $this->includeNullableColumns = $nullables;
        $this->overwrite = $overwrite;
    }

    /**
     * Build relation function.
     *
     *
     * @return string
     */
    public function buildRelationFunction(string $column, $relationMethod = null)
    {
        $relationName = optional($relationMethod)['name'] ?? Str::camel(str_replace('_id', '', $column));
        $foreignCallback = '\\App\\REPLACE_THIS::factory()';

        try {
            $relatedModel = get_class($this->modelInstance->$relationName()->getRelated());

            return str_replace('App\\REPLACE_THIS', $relatedModel, $foreignCallback);
        } catch (\Exception $e) {
            return $foreignCallback;
        }
    }

    public function generate($model): ?string
    {
        if (! $modelClass = $this->modelExists($model)) {
            return null;
        }

        $factoryPath = $this->factoryPath($modelClass);

        if (! $this->overwrite && $this->factoryExists($factoryPath)) {
            return null;
        }

        $this->modelInstance = new $modelClass();

        $code = Artisan::call('model:show', ['model' => $modelClass, '--json']);
        if ($code !== 0) {
            return null;
        }

        $json = json_decode(Artisan::output(), true);
        if (! $json) {
            return null;
        }

        collect($this->columns())
            ->merge($this->relationships())
            ->filter()
            ->unique()
            ->values()
            ->pipe(function ($properties) use ($factoryPath, $modelClass) {
                $this->writeFactoryFile($factoryPath, $properties, $modelClass);
                $this->addFactoryTrait($modelClass);
            });

        return $factoryPath;
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
            while (! $found) {
                $found = Str::contains($lines[$line], '{'); // found closing curly
                $line++; // advance one more line to place the trait...
            }

            array_splice($lines, $line, 0, '    use HasFactory;'.PHP_EOL);
        } else {
            $line = $traits[0]->getStartLine();
            array_splice($lines, $line + 1, 0, '    use HasFactory;');
        }

        File::put($path, implode(PHP_EOL, $lines));
    }

    /**
     * Check if factory already exists.
     *
     * @param  string  $name
     * @return bool|string
     */
    protected function factoryExists($path): bool
    {
        return File::exists($path);
    }

    /**
     * Map database table column definition to faker value.
     */
    protected function mapColumn(array $column): array
    {
        $key = $column['name'];

        if (! $this->shouldBeIncluded($column)) {
            return $this->mapToFactory($key);
        }

        if ($column['foreign']) {
            return $this->mapToFactory(
                $key,
                $this->buildRelationFunction($key)
            );
        }

        if ($key === 'password') {
            return $this->mapToFactory($key, "Hash::make('password')");
        }

        $value = $column['unique']
            ? '$this->faker->unique()->'
            : '$this->faker->';

        return $this->mapToFactory($key, $value.$this->mapToFaker($column));
    }

    protected function mapToFactory($key, $value = null): array
    {
        return [
            $key => is_null($value) ? $value : "'{$key}' => $value",
        ];
    }

    /**
     * Map name to faker method.
     *
     * @param  Column  $column
     * @return string
     */
    protected function mapToFaker(array $column)
    {
        return $this->typeGuesser->guess(
            $column['name'],
            $column['type'],
            $column['length']
        );
    }

    /**
     * Check if the given model exists.
     *
     * @param  string  $name
     * @return bool|string
     */
    protected function modelExists($name): bool
    {
        if (class_exists($modelClass = $this->qualifyClass($name))) {
            return $modelClass;
        }

        // TODO: this check should happen before calling this service class...
        throw new \UnexpectedValueException('could not find model ['.$name.']');
    }

    /**
     * Parse the class name and format according to the root namespace.
     *
     * @param  string  $name
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
            trim($rootNamespace, '\\').'\\'.$name
        );
    }

    /**
     * Get properties via reflection from methods.
     */
    protected function relationships(): Collection
    {
        Artisan::call('', ['--nowrite' => true])->output();

        return Analyzer::relations($this->modelInstance)
            ->filter(function (RelationMethod $method) {
                return $method->returnType() === BelongsTo::class;
            })
            ->mapWithKeys(function (RelationMethod $method) {
                $property = $method->foreignKey();

                return [$property => "'$property' => ".$this->buildRelationFunction($property, $method)];
            });
    }

    /**
     * Check if a given column should be included in the factory.
     */
    protected function shouldBeIncluded(array $column): bool
    {
        $shouldBeIncluded = ($column['nullable'] || $this->includeNullableColumns)
            && ! $column['auto_increment']
            && ! $column['primary'];

        if (! $this->modelInstance->usesTimestamps()) {
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
            && ! in_array($column['name'], $timestamps);
    }

    /**
     * Write the model factory file using the given definition and path.
     */
    protected function writeFactoryFile(string $path, array $data, string $modelClass): void
    {
        File::ensureDirectoryExists(dirname($path));

        $definition = '';
        foreach ($data as $value) {
            $definition .= PHP_EOL.'            '.$value.',';
        }

        $factoryQualifiedName = \Illuminate\Database\Eloquent\Factories\Factory::resolveFactoryName($modelClass);
        $factoryNamespace = Str::beforeLast($factoryQualifiedName, '\\');
        $contents = File::get(__DIR__.'/../stubs/factory.stub');
        $contents = str_replace('{{ factoryNamespace }}', $factoryNamespace, $contents);
        $contents = str_replace('{{ namespacedModel }}', $modelClass, $contents);
        $contents = str_replace('{{ model }}', class_basename($modelClass), $contents);
        $contents = str_replace('            //', trim($definition, PHP_EOL), $contents);

        File::put($path, $contents);
    }

    private function columns(): Collection
    {
        return collect()->mapWithKeys(fn ($column) => $this->mapColumn($column));
    }

    private function factoryPath($model): string
    {
        $subDirectory = Str::of($model)
            ->replaceFirst('App\\Models\\', '')
            ->replaceFirst('App\\', '');

        return database_path('factories/'.str_replace('\\', '/', $subDirectory).'Factory.php');
    }
}
