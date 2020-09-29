<?php

namespace Shift\FactoryGenerator\Tests;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shift\FactoryGenerator\Tests\Fixtures\Models\Car;
use Shift\FactoryGenerator\Tests\Fixtures\Models\Book;
use Shift\FactoryGenerator\Tests\Fixtures\Models\User;
use Shift\FactoryGenerator\Tests\Fixtures\Models\Habit;

class PrefillFactoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase($this->app);

        $this->beforeApplicationDestroyed(function () {
            File::cleanDirectory(app_path());
            File::cleanDirectory(database_path('factories'));
        });
    }

    /**
     * Set up the database.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function setUpDatabase($app)
    {
        $this->loadMigrationsFrom([
            '--path' => realpath(__DIR__ . '/migrations'),
            '--realpath' => true,
        ]);
    }

    /** @test */
    public function it_asks_if_a_model_shall_be_created_if_it_does_not_yet_exist()
    {
        $this->artisan('generate:factory', ['models' => "App\NonExistent"])
            ->expectsOutput('Model created successfully.');
    }

    /** @test */
    public function it_asks_if_a_factory_should_be_overridden_if_it_already_exists()
    {
        $this->artisan('make:factory', ['name' => 'ExistsFactory']);
        $this->artisan('make:model', ['name' => 'Exists']);

        $this->artisan('generate:factory', ['models' => 'Exists'])
            ->expectsQuestion('A factory file for Exists already exists, do you wish to overwrite the existing file?', false)
            ->expectsOutput('Canceled blueprint creation!');
    }

    /** @test */
    public function it_can_create_prefilled_factories_for_a_model()
    {
        $this->artisan('generate:factory', [
            'models' => Habit::class,
            '--no-interaction' => true,
        ])->expectsOutput('Factory blueprint created!');

        $this->assertFileExists(database_path('factories/HabitFactory.php'));
    }

    /** @test */
    public function it_can_associate_models_through_their_relationship()
    {
        $this->artisan('generate:factory', [
            'models' => Habit::class,
            '--no-interaction' => true,
        ])->expectsOutput('Factory blueprint created!');

        $this->assertFileExists(database_path('factories/HabitFactory.php'));
        $this->assertTrue(Str::contains(
            File::get(database_path('factories/HabitFactory.php')),
            "'user_id' => factory(Shift\FactoryGenerator\Tests\Fixtures\Models\User::class)->lazy(),"
        ));
    }

    /** @test */
    public function it_can_associate_models_through_their_relationship_methods_without_touching_the_db()
    {
        $this->artisan('generate:factory', [
            'models' => Car::class,
            '--no-interaction' => true,
        ])->expectsOutput('Factory blueprint created!');

        $this->assertFileExists(database_path('factories/CarFactory.php'));
        $this->assertTrue(Str::contains(
            File::get(database_path('factories/CarFactory.php')),
            "'owner_id' => factory(Shift\FactoryGenerator\Tests\Fixtures\Models\User::class)->lazy(),"
        ));
    }

    /** @test */
    public function it_prints_an_error_if_no_database_info_could_be_found()
    {
        $this->artisan('generate:factory', [
            'models' => Book::class,
            '--no-interaction' => true,
        ])->expectsOutput('We could not find any data for your factory. Did you `php artisan migrate` already?');
    }

    /** @test */
    public function it_can_include_nullable_properties_in_factories()
    {
        $this->artisan('generate:factory', [
            'models' => Car::class,
            '--no-interaction' => true,
            '--include-nullable' => true,
        ])->expectsOutput('Factory blueprint created!');

        $this->assertFileExists(database_path('factories/CarFactory.php'));

        $this->assertTrue(Str::contains(
            File::get(database_path('factories/CarFactory.php')),
            "'factory_year' => \$faker->randomNumber,"
        ));
    }

    /** @test */
    public function it_does_not_include_the_models_created_at_updated_at_or_deleted_at_timestamps_even_nullable_values_are_requested()
    {
        $this->artisan('generate:factory', [
            'models' => Car::class,
            '--no-interaction' => true,
            '--include-nullable' => true,
        ])->expectsOutput('Factory blueprint created!');

        $this->assertFileExists(database_path('factories/CarFactory.php'));
        $this->assertFalse(Str::contains(
            File::get(database_path('factories/CarFactory.php')),
            "'created_at' => \$faker,"
        ));
        $this->assertFalse(Str::contains(
            File::get(database_path('factories/CarFactory.php')),
            "'updated_at' => \$faker,"
        ));
        $this->assertFalse(Str::contains(
            File::get(database_path('factories/CarFactory.php')),
            "'deleted_at' => \$faker,"
        ));
    }

    /** @test */
    public function it_identifies_belongs_to_relations_through_relation_methods()
    {
        $this->artisan('generate:factory', [
            'models' => Car::class,
            '--no-interaction' => true,
            '--include-nullable' => true,
        ])->expectsOutput('Factory blueprint created!');

        $this->assertFileExists($path = database_path('factories/CarFactory.php'));
        $this->assertTrue(Str::contains(
            File::get($path),
            "'previous_owner_id' => factory(" . User::class . '::class)->lazy(),'
        ));
    }

    /** @test */
    public function it_can_correctly_prefill_password_columns()
    {
        $this->artisan('generate:factory', [
            'models' => User::class,
            '--no-interaction' => true,
        ])->expectsOutput('Factory blueprint created!');

        $this->assertFileExists(database_path('factories/UserFactory.php'));
        $this->assertTrue(Str::contains(
            File::get(database_path('factories/UserFactory.php')),
            "'password' => bcrypt('password'),"
        ));
    }
}
