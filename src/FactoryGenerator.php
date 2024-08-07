<?php

namespace Shift\FactoryGenerator;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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

        $code = Artisan::call('model:show', ['model' => $modelClass, '--json' => true]);
        if ($code !== 0) {
            return null;
        }

        $json = json_decode(Artisan::output(), true);
        if (! $json) {
            return null;
        }

        $foreign_keys = $this->modelInstance->getConnection()->getSchemaBuilder()->getForeignKeys($json['table']);

        collect($this->columns($json['attributes'], $foreign_keys))
            ->merge($this->relationships($json['relations']))
            ->filter()
            ->unique()
            ->values()
            ->pipe(function ($properties) use ($factoryPath, $modelClass) {
                $this->writeFactoryFile($factoryPath, $properties->all(), $modelClass);
                $this->addFactoryTrait($modelClass);
            });

        return $factoryPath;
    }

    protected function addFactoryTrait($modelClass)
    {
        $traits = class_uses_recursive($modelClass);
        if (in_array('Illuminate\\Database\\Eloquent\\Factories\\HasFactory', $traits)) {
            return;
        }

        $path = (new \ReflectionClass($modelClass))->getFileName();

        $contents = File::get($path);

        $tokens = collect(\PhpToken::tokenize($contents));

        $class = $tokens->first(fn (\PhpToken $token) => $token->id === T_CLASS);
        $import = $tokens->first(fn (\PhpToken $token) => $token->id === T_USE);

        $pos = strpos($contents, '{', $class->pos) + 1;
        $replacement = PHP_EOL.'    use HasFactory;'.PHP_EOL;
        $contents = substr_replace($contents, $replacement, $pos, 0);

        $anchor = $import ?? $class;

        $contents = substr_replace(
            $contents,
            'use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;'.PHP_EOL,
            $anchor->pos,
            0
        );

        File::put($path, $contents);
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
            return $this->factoryTuple($key);
        }

        // TODO: probably belongs elsewhere...
        if ($key === 'password') {
            return $this->factoryTuple($key, "Hash::make('password')");
        }

        $value = $column['unique']
            ? '$this->faker->unique()->'
            : '$this->faker->';

        return $this->factoryTuple($key, $value.$this->mapToFaker($column));
    }

    protected function factoryTuple($key, $value = null): array
    {
        return [
            $key => is_null($value) ? $value : "'{$key}' => $value",
        ];
    }

    /**
     * Map name to faker method.
     */
    protected function mapToFaker(array $column): string
    {
        return $this->typeGuesser->guess(
            $column['name'],
            $column['type'],
            $column['length']
        );
    }

    /**
     * Check if the given model exists.
     */
    protected function modelExists(string $name): string
    {
        if (class_exists($modelClass = $this->qualifyClass($name))) {
            return $modelClass;
        }

        // TODO: this check should happen before calling this service class...
        throw new \UnexpectedValueException('could not find model ['.$name.']');
    }

    /**
     * Parse the class name and format according to the root namespace.
     */
    protected function qualifyClass(string $name): string
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
     * Get properties for relationships where we can build
     * other factories. Currently, that's simply BelongsTo.
     */
    protected function relationships(array $relationships): Collection
    {
        return collect($relationships)
            ->filter(fn ($relationship) => $relationship['type'] === 'BelongsTo')
            ->mapWithKeys(function ($relationship) {
                $property = $this->modelInstance->{$relationship['name']}()->getForeignKeyName();

                return [$property => "'$property' => \\".$relationship['related'].'::factory()'];
            });
    }

    /**
     * Check if a given column should be included in the factory.
     */
    protected function shouldBeIncluded(array $column): bool
    {
        $shouldBeIncluded = (! $column['nullable'] || $this->includeNullableColumns)
            && ! $column['increments']
            && ! $column['foreign']
            && $column['name'] !== $this->modelInstance->getKeyName();

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

        $factoryQualifiedName = \Illuminate\Database\Eloquent\Factories\Factory::resolveFactoryName($modelClass);
        $factoryNamespace = Str::beforeLast($factoryQualifiedName, '\\');
        $contents = File::get(__DIR__.'/../stubs/factory.stub');
        $contents = str_replace('{{ factoryNamespace }}', $factoryNamespace, $contents);
        $contents = str_replace('{{ namespacedModel }}', $modelClass, $contents);
        $contents = str_replace('{{ model }}', class_basename($modelClass), $contents);
        $definitions = array_map(fn ($value) => '            '.$value.',', $data);
        $contents = str_replace('            //', implode(PHP_EOL, $definitions), $contents);

        File::put($path, $contents);
    }

    private function appendColumnData(array $column, array $foreignKeys): array
    {
        $column['foreign'] = in_array($column['name'], $foreignKeys);
        $column['length'] = null;

        if (str_contains($column['type'], '(')) {
            $column['length'] = Str::between($column['type'], '(', ')');
            $column['type'] = Str::before($column['type'], '(');
        }

        return $column;
    }

    private function columns(array $attributes, array $foreignKeys): Collection
    {
        return collect($attributes)
            ->reject(fn ($column) => is_null($column['type']))
            ->map(fn ($column) => $this->appendColumnData($column, $foreignKeys))
            ->mapWithKeys(fn ($column) => $this->mapColumn($column));
    }

    private function factoryPath($model): string
    {
        $subDirectory = Str::of($model)
            ->replaceFirst('App\\Models\\', '')
            ->replaceFirst('App\\', '');

        return database_path('factories/'.str_replace('\\', '/', $subDirectory).'Factory.php');
    }
}
