<?php

namespace Shift\FactoryGenerator;

use Faker\Generator as Faker;
use Faker\Provider\Base;
use Illuminate\Support\Str;

class TypeGuesser
{
    /**
     * @var \Faker\Generator
     */
    protected $generator;

    /**
     * Create a new TypeGuesser instance.
     */
    public function __construct(Faker $generator)
    {
        $this->generator = $generator;
    }

    public function guess(string $name, string $type, ?string $size = null): string
    {
        $name = Str::of($name)->lower();

        if ($name->endsWith('_id')) {
            return 'randomDigitNotNull()';
        }

        if ($typeNameGuess = $this->guessBasedOnName($name->__toString(), $size)) {
            return $typeNameGuess;
        }

        if ($nativeName = $this->nativeNameFor($name->replace('_', ''))) {
            return $nativeName.'()';
        }

        if ($name->endsWith('_url')) {
            return 'url()';
        }

        return $this->guessBasedOnType($type, $size);
    }

    /**
     * Get type guess.
     */
    protected function guessBasedOnName(string $name, ?string $size = null): ?string
    {
        switch ($name) {
            case 'login':
                return 'userName()';
            case 'email_address':
            case 'emailaddress':
                return 'email()';
            case 'phone':
            case 'telephone':
            case 'telnumber':
                return 'phoneNumber()';
            case 'town':
                return 'city()';
            case 'postalcode':
            case 'postal_code':
            case 'zipcode':
            case 'zip_code':
                return 'postcode()';
            case 'province':
            case 'county':
                return $this->predictCountyType();
            case 'country':
                return $this->predictCountryType($size);
            case 'currency':
                return 'currencyCode()';
            case 'website':
                return 'url()';
            case 'companyname':
            case 'company_name':
            case 'employer':
                return 'company()';
            case 'title':
                return $this->predictTitleType($size);
            default:
                return null;
        }
    }

    /**
     * Get native name for the given string.
     */
    protected function nativeNameFor(string $lookup): ?string
    {
        static $fakerMethodNames = [];

        if (empty($fakerMethodNames)) {
            $fakerMethodNames = collect($this->generator->getProviders())
                ->flatMap(function (Base $provider) {
                    return $this->getNamesFromProvider($provider);
                })
                ->unique()
                ->toArray();
        }

        if (isset($fakerMethodNames[$lookup])) {
            return $fakerMethodNames[$lookup];
        }

        return null;
    }

    /**
     * Get public methods as a lookup pair.
     */
    protected function getNamesFromProvider(Base $provider): array
    {
        return collect(get_class_methods($provider))
            ->reject(fn (string $methodName) => Str::startsWith($methodName, '__'))
            ->mapWithKeys(fn (string $methodName) => [Str::lower($methodName) => $methodName])
            ->all();
    }

    /**
     * Try to guess the right faker method for the given type.
     */
    protected function guessBasedOnType(string $type, ?string $size): string
    {
        switch ($type) {
            case 'boolean':
                return 'boolean()';
            case 'bigint':
            case 'integer':
            case 'smallint':
                return 'randomNumber('.$size.')';
            case 'date_mutable':
            case 'date_immutable':
                return 'date()';
            case 'datetime_mutable':
            case 'datetime_immutable':
                return 'dateTime()';
            case 'decimal':
            case 'float':
                return 'randomFloat('.$size.')';
            case 'text':
                return 'text()';
            case 'time_mutable':
            case 'time_immutable':
                return 'time()';
            default:
                return 'word()';
        }
    }

    /**
     * Predicts county type by locale.
     */
    protected function predictCountyType(): string
    {
        if ($this->generator->locale == 'en_US') {
            return "sprintf('%s County', \$faker->city())";
        }

        return 'state()';
    }

    /**
     * Predicts country code based on $size.
     */
    protected function predictCountryType(int $size): string
    {
        switch ($size) {
            case 2:
                return 'countryCode()';
            case 3:
                return 'countryISOAlpha3()';
            case 5:
            case 6:
                return 'locale()';
        }

        return 'country()';
    }

    /**
     * Predicts type of title by $size.
     */
    protected function predictTitleType(?int $size): string
    {
        if ($size === null || $size <= 10) {
            return 'title()';
        }

        return 'sentence()';
    }
}
