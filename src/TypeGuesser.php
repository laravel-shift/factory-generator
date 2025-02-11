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
        if (str_ends_with($name, '_token')) {
            return 'sha1()';
        }

        return match ($name) {
            'login' => 'userName()',
            'email_address', 'emailaddress' => 'email()',
            'phone', 'telephone', 'telnumber' => 'phoneNumber()',
            'town' => 'city()',
            'postalcode', 'postal_code', 'zipcode', 'zip_code' => 'postcode()',
            'province', 'county' => $this->predictCountyType(),
            'country' => $this->predictCountryType($size),
            'currency' => 'currencyCode()',
            'website' => 'url()',
            'companyname', 'company_name', 'employer' => 'company()',
            'title' => $this->predictTitleType($size),
            default => null,
        };
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
        $precision = 0;
        if (str_contains($size, ',')) {
            [$size, $precision] = explode(',', $size, 2);
        }

        $type = match ($type) {
            'tinyint(1)', 'bit', 'varbit', 'boolean', 'bool' => 'boolean',
            'varchar(max)', 'nvarchar(max)', 'text', 'ntext', 'tinytext', 'mediumtext', 'longtext' => 'text',
            'integer', 'int', 'int4', 'smallint', 'int2', 'tinyint', 'mediumint', 'bigint', 'int8' => 'integer',
            'date' => 'date',
            'decimal', 'float', 'real', 'float4', 'double', 'float8' => 'float',
            'time', 'timetz' => 'time',
            'datetime', 'datetime2', 'smalldatetime','datetimeoffset' => 'datetime',
            'timestamp', 'timestamptz' => 'timestamp',
            'json', 'jsonb' => 'json',
            'uuid', 'uniqueidentifier' => 'uuid',
            'inet', 'inet4', 'cidr' => 'ip_address',
            'macaddr', 'macaddr8' => 'mac_address',
            'year' => 'year',
            'char', 'bpchar', 'nchar' => 'char',
            'varchar', 'nvarchar' => 'string',
            'binary', 'varbinary', 'bytea', 'image', 'blob', 'tinyblob', 'mediumblob', 'longblob' => 'binary',
            'geometry', 'geometrycollection', 'linestring', 'multilinestring', 'point', 'multipoint', 'polygon', 'multipolygon' => 'geometry',
            'geography' => 'geography',

            // 'enum => 'enum',
            // 'set' => 'set',
            // 'money', 'smallmoney' => 'money',
            // 'xml' => 'xml',
            // 'interval' => 'interval',
            // 'box', 'circle', 'line', 'lseg', 'path' => 'geometry',
            // 'tsvector', 'tsquery' => 'text',
            default => $type,
        };

        if ($type === 'float' && $precision == 0) {
            $type = 'integer';
        }

        return match ($type) {
            'boolean' => 'boolean()',
            'char' => 'randomLetter()',
            'date' => 'date()',
            'datetime' => 'dateTime()',
            'float' => 'randomFloat('.$precision.')',
            'inet6' => 'ipv6()',
            'integer', 'number' => 'randomNumber('.$size.')',
            'ip_address' => 'ipv4()',
            'mac_address' => 'macAddress()',
            'text' => 'text()',
            'time' => 'time()',
            'timestamp' => 'unixTime()',
            'uuid' => 'uuid()',
            'year' => 'year()',
            default => 'word()',
        };
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
    protected function predictCountryType(?int $size): string
    {
        return match ($size) {
            2 => 'countryCode()',
            3 => 'countryISOAlpha3()',
            5, 6 => 'locale()',
            default => 'country()',
        };
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
