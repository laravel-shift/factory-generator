<?php

namespace Shift\FactoryGenerator;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
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
     * @var string
     */
    protected static $default = 'word()';

    /**
     * @var array
     */
    protected $fakerMethodNames = [];

    /**
     * Create a new TypeGuesser instance.
     *
     * @param \Faker\Generator $generator
     */
    public function __construct(Faker $generator)
    {
        $this->generator = $generator;
    }

    /**
     * @param string $name
     * @param Type $type
     * @param int|null $size Length of field, if known
     *
     * @return string
     */
    public function guess($name, Type $type, $size = null)
    {
        $name = Str::of($name)->lower();

        if ($name->endsWith('_id')) {
            return 'integer()';
        }

        $lookup = $name->replace('_', '');

        if ($typeNameGuess = $this->guessBasedOnName($lookup, $size)) {
            return $typeNameGuess;
        }

        if ($nativeName = $this->nativeNameFor($lookup)) {
            return $nativeName . '()';
        }

        if ($name->endsWith('_url')) {
            return 'url()';
        }

        return $this->guessBasedOnType($type, $size);
    }

    /**
     * Get native name for the given string.
     *
     * @param string $lookup
     *
     * @return string|null
     */
    protected function nativeNameFor(string $lookup)
    {
        if (empty($this->fakerMethodNames)) {
            $this->fakerMethodNames = collect($this->generator->getProviders())
                ->flatMap(function(Base $provider) {
                    return get_class_methods($provider);
                })
                ->reject(function(string $methodName) {
                    return Str::startsWith($methodName, '__');
                })
                ->unique()
                ->mapWithKeys(function(string $methodName) {
                    return [Str::lower($methodName) => $methodName];
                })
                ->toArray();
        }

        if (isset($this->fakerMethodNames[$lookup])) {
            return $this->fakerMethodNames[$lookup];
        }

        return null;
    }

    /**
     * Get type guess.
     *
     * @param string $name
     * @param int|null $size
     *
     * @return string|null
     */
    protected function guessBasedOnName(string $name, $size = null)
    {
        switch ($name) {
            case 'login':
                return 'userName()';
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
            case 'employer':
                return 'company()';
            case 'title':
                return $this->predictTitleType($size);
            default:
                return null;
        }
    }

    /**
     * Try to guess the right faker method for the given type.
     *
     * @param Type $type
     * @param int|null $size
     *
     * @return string
     */
    protected function guessBasedOnType(Type $type, $size)
    {
        $typeName = $type->getName();

        switch ($typeName) {
            case Types::BOOLEAN:
                return 'boolean()';
            case Types::BIGINT:
            case Types::INTEGER:
            case Types::SMALLINT:
                return 'randomNumber(' . $size . ')';
            case Types::DATE_MUTABLE:
            case Types::DATE_IMMUTABLE:
                return 'date()';
            case Types::DATETIME_MUTABLE:
            case Types::DATETIME_IMMUTABLE:
                return 'dateTime()';
            case Types::DECIMAL:
            case Types::FLOAT:
                return 'randomFloat(' . $size . ')';
            case Types::TEXT:
                return 'text()';
            case Types::TIME_MUTABLE:
            case Types::TIME_IMMUTABLE:
                return 'time()';
            default:
                return self::$default;
        }
    }

    /**
     * Predicts county type by locale.
     *
     * @return string
     */
    protected function predictCountyType()
    {
        if ('en_US' == $this->generator->locale) {
            return "sprintf('%s County', \$faker->city())";
        }

        return 'state()';
    }

    /**
     * Predicts country code based on $size.
     *
     * @param int $size
     *
     * @return string
     */
    protected function predictCountryType($size)
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
     *
     * @param int $size
     *
     * @return string
     */
    protected function predictTitleType($size)
    {
        if (null === $size || $size <= 10) {
            return 'title()';
        }

        return 'sentence()';
    }
}
