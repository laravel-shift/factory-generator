<?php

namespace Shift\FactoryGenerator;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use SplFileInfo;

class GenerateCommand extends Command
{
    protected $signature = 'generate:factory {models?*}
                        {--p|path= : Load models from a specific path}
                        {--i|include-nullable : Include nullable columns in your factory}
                        {--f|force : Overwrite any existing factory}';

    protected $description = 'Generate factories for existing models';

    public function handle()
    {
        $directory = $this->resolveModelPath();
        $models = $this->argument('models');

        if (!File::exists($directory)) {
            $this->error("Path does not exist [$directory]");

            return 1;
        }

        $generator = resolve(FactoryGenerator::class, ['nullables' => $this->option('include-nullable'), 'overwrite' => $this->option('force')]);

        $this->loadModels($directory, $models)
            ->filter(function ($model) {
                return (new ReflectionClass($model))->isSubclassOf(Model::class);
            })
            ->each(function ($model) use ($generator) {
                $factory = $generator->generate($model);

                if ($factory) {
                    $this->line('<info>Model factory created:</info> ' . $factory);
                } else {
                    $this->line('<error>Failed to create factory for model:</error> ' . $model);
                }
            });

        return 0;
    }

    protected function loadModels(string $directory, array $models = []): Collection
    {
        if (!empty($models)) {
            return collect($models)->map(function ($name) use ($directory) {
                if (strpos($name, '\\') !== false) {
                    return $name;
                }

                return str_replace(
                    [DIRECTORY_SEPARATOR, basename($this->laravel->path()) . '\\'],
                    ['\\', $this->laravel->getNamespace()],
                    basename($this->laravel->path()) . DIRECTORY_SEPARATOR . $name
                );
            });
        }

        return collect(File::files($directory))->map(function (SplFileInfo $file) {
            preg_match('/namespace\s.*/', $file->getContents(), $matches);
            return str_replace(
                    ['namespace ', ';'],
                    [''],
                    trim($matches[0])
                ) . "\\{$file->getBasename('.php')}";
        });
    }

    protected function resolveModelPath(): string
    {
        $path = $this->option('path');
        if (!is_null($path)) {
            return base_path($path);
        }

        if (File::isDirectory(app_path('Models'))) {
            return app_path('Models');
        }

        return app_path();
    }
}
