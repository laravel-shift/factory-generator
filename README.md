# Laravel Model Factory Generator
This package generates model factories from existing models using the new [class-based factories](https://laravel.com/docs/8.x/database-testing#writing-factories) in Laravel 8.


## Installation
You may install this package via composer by running:

```sh
composer require --dev laravel-shift/factory-generator
```

The package will automatically register itself.


## Usage
This package adds an artisan command for generating model factories.

Without any arguments, this command will generate model factories for all existing models within your Laravel application:

```sh
php artisan generate:factory
```

Similar to Laravel, this will search for models within the `app/Models` folder, or if that folder does not exist, within the `app` folder.

To generate factories for models within a different folder, you may pass the `--path` option (or `-p`).

```sh
php artisan generate:factory --path=some/Other/Path
```

To generate a factory for a single model, you may pass the model name:

```sh
php artisan generate:factory User
```

By default _nullable_ columns are not included in the factory definition. If you want to include _nullable_ columns you may set the `--include-nullable` option (or `-i`).

```sh
php artisan generate:factory -i User
```


## Attribution
This package was original forked from [Naoray/laravel-factory-prefill](https://github.com/Naoray/laravel-factory-prefill) by [Krishan König](https://github.com/Naoray).

It has diverged to support the latest version of Laravel and to power part of the automation by the [Tests Generator](https://laravelshift.com/laravel-test-generator).


## Contributing
Contributions should be submitted to the `master` branch. Any submissions should be complete with tests and adhere to the [PSR-2 code style](https://www.php-fig.org/psr/psr-2/). You may also contribute by [opening an issue](https://github.com/laravel-shift/factory-generator/issues).
