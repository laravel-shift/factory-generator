<?php

namespace Tests;

use Shift\FactoryGenerator\TypeGuesser;

class TypeGuesserTest extends TestCase
{
    /**
     * @var TypeGuesser
     */
    protected $typeGuesser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typeGuesser = resolve(TypeGuesser::class);
    }

    /** @test */
    public function it_can_guess_boolean_values_by_type()
    {
        $this->assertEquals('boolean()', $this->typeGuesser->guess('is_verified', 'boolean'));
    }

    /** @test */
    public function it_can_guess_random_integer_values_by_type()
    {
        $this->assertEquals('randomNumber()', $this->typeGuesser->guess('integer', 'integer'));
        $this->assertEquals('randomNumber(10)', $this->typeGuesser->guess('integer', 'integer', 10));

        $this->assertEquals('randomNumber()', $this->typeGuesser->guess('big_int', 'bigint'));
        $this->assertEquals('randomNumber(10)', $this->typeGuesser->guess('big_int', 'bigint', 10));

        $this->assertEquals('randomNumber()', $this->typeGuesser->guess('small_int', 'smallint'));
        $this->assertEquals('randomNumber(10)', $this->typeGuesser->guess('small_int', 'smallint', 10));
    }

    /** @test */
    public function it_can_guess_random_decimal_values_by_type()
    {
        $this->assertEquals('randomFloat()', $this->typeGuesser->guess('decimal_value', 'decimal'));
        $this->assertEquals('randomFloat(10)', $this->typeGuesser->guess('decimal_value', 'decimal', 10));
    }

    /** @test */
    public function it_can_guess_random_float_values_by_type()
    {
        $this->assertEquals('randomFloat()', $this->typeGuesser->guess('float_value', 'float'));
        $this->assertEquals('randomFloat(10)', $this->typeGuesser->guess('float_value', 'float', 10));
    }

    /** @test */
    public function it_can_guess_date_time_values_by_type()
    {
        $this->assertEquals('dateTime()', $this->typeGuesser->guess('done_at', 'datetime'));
        $this->assertEquals('date()', $this->typeGuesser->guess('birthdate', $this->getType(Types::DATE_IMMUTABLE)));
        $this->assertEquals('time()', $this->typeGuesser->guess('closing_at', $this->getType(Types::TIME_IMMUTABLE)));
    }

    /** @test */
    public function it_can_guess_text_values_by_type()
    {
        $this->assertEquals('text()', $this->typeGuesser->guess('body', 'text'));
    }

    /** @test */
    public function it_can_guess_name_values()
    {
        $this->assertEquals('name()', $this->typeGuesser->guess('name', 'no-op'));
    }

    /** @test */
    public function it_can_guess_first_name_values()
    {
        $this->assertEquals('firstName()', $this->typeGuesser->guess('first_name', 'no-op'));
        $this->assertEquals('firstName()', $this->typeGuesser->guess('firstname', 'no-op'));
    }

    /** @test */
    public function it_can_guess_last_name_values()
    {
        $this->assertEquals('lastName()', $this->typeGuesser->guess('last_name', 'no-op'));
        $this->assertEquals('lastName()', $this->typeGuesser->guess('lastname', 'no-op'));
    }

    /** @test */
    public function it_can_guess_user_name_values()
    {
        $this->assertEquals('userName()', $this->typeGuesser->guess('username', 'no-op'));
        $this->assertEquals('userName()', $this->typeGuesser->guess('user_name', 'no-op'));
        $this->assertEquals('userName()', $this->typeGuesser->guess('login', 'no-op'));
    }

    /** @test */
    public function it_can_guess_email_values()
    {
        $this->assertEquals('email()', $this->typeGuesser->guess('email', 'no-op'));
        $this->assertEquals('email()', $this->typeGuesser->guess('emailaddress', 'no-op'));
        $this->assertEquals('email()', $this->typeGuesser->guess('email_address', 'no-op'));
    }

    /** @test */
    public function it_can_guess_phone_number_values()
    {
        $this->assertEquals('phoneNumber()', $this->typeGuesser->guess('phonenumber', 'no-op'));
        $this->assertEquals('phoneNumber()', $this->typeGuesser->guess('phone_number', 'no-op'));
        $this->assertEquals('phoneNumber()', $this->typeGuesser->guess('phone', 'no-op'));
        $this->assertEquals('phoneNumber()', $this->typeGuesser->guess('telephone', 'no-op'));
        $this->assertEquals('phoneNumber()', $this->typeGuesser->guess('telnumber', 'no-op'));
    }

    /** @test */
    public function it_can_guess_address_values()
    {
        $this->assertEquals('address()', $this->typeGuesser->guess('address', 'no-op'));
    }

    /** @test */
    public function it_can_guess_city_values()
    {
        $this->assertEquals('city()', $this->typeGuesser->guess('city', 'no-op'));
        $this->assertEquals('city()', $this->typeGuesser->guess('town', 'no-op'));
    }

    /** @test */
    public function it_can_guess_street_address_values()
    {
        $this->assertEquals('streetAddress()', $this->typeGuesser->guess('street_address', 'no-op'));
        $this->assertEquals('streetAddress()', $this->typeGuesser->guess('streetAddress', 'no-op'));
    }

    /** @test */
    public function it_can_guess_postcode_values()
    {
        $this->assertEquals('postcode()', $this->typeGuesser->guess('postcode', 'no-op'));
        $this->assertEquals('postcode()', $this->typeGuesser->guess('zipcode', 'no-op'));
        $this->assertEquals('postcode()', $this->typeGuesser->guess('postalcode', 'no-op'));
        $this->assertEquals('postcode()', $this->typeGuesser->guess('postal_code', 'no-op'));
        $this->assertEquals('postcode()', $this->typeGuesser->guess('postalCode', 'no-op'));
    }

    /** @test */
    public function it_can_guess_state_values()
    {
        $this->assertEquals('state()', $this->typeGuesser->guess('state', 'no-op'));
        $this->assertEquals('state()', $this->typeGuesser->guess('province', 'no-op'));
        $this->assertEquals('state()', $this->typeGuesser->guess('county', 'no-op'));
    }

    /** @test */
    public function it_can_guess_country_values()
    {
        $this->assertEquals('countryCode()', $this->typeGuesser->guess('country', 'no-op', 2));
        $this->assertEquals('countryISOAlpha3()', $this->typeGuesser->guess('country', 'no-op', 3));
        $this->assertEquals('country()', $this->typeGuesser->guess('country', 'no-op'));
    }

    /** @test */
    public function it_can_guess_locale_values()
    {
        $this->assertEquals('locale()', $this->typeGuesser->guess('country', 'no-op', 5));
        $this->assertEquals('locale()', $this->typeGuesser->guess('country', 'no-op', 6));
        $this->assertEquals('locale()', $this->typeGuesser->guess('locale', 'no-op'));
    }

    /** @test */
    public function it_can_guess_currency_code_values()
    {
        $this->assertEquals('currencyCode()', $this->typeGuesser->guess('currency', 'no-op'));
        $this->assertEquals('currencyCode()', $this->typeGuesser->guess('currencycode', 'no-op'));
        $this->assertEquals('currencyCode()', $this->typeGuesser->guess('currency_code', 'no-op'));
    }

    /** @test */
    public function it_can_guess_url_values()
    {
        $this->assertEquals('url()', $this->typeGuesser->guess('website', 'no-op'));
        $this->assertEquals('url()', $this->typeGuesser->guess('url', 'no-op'));
        $this->assertEquals('url()', $this->typeGuesser->guess('twitter_url', 'no-op'));
        $this->assertEquals('url()', $this->typeGuesser->guess('endpoint_url', 'no-op'));
    }

    /** @test */
    public function it_can_guess_image_url_values()
    {
        $this->assertEquals('imageUrl()', $this->typeGuesser->guess('image_url', 'no-op'));
    }

    /** @test */
    public function it_can_guess_company_values()
    {
        $this->assertEquals('company()', $this->typeGuesser->guess('company', 'no-op'));
        $this->assertEquals('company()', $this->typeGuesser->guess('companyname', 'no-op'));
        $this->assertEquals('company()', $this->typeGuesser->guess('company_name', 'no-op'));
        $this->assertEquals('company()', $this->typeGuesser->guess('employer', 'no-op'));
    }

    /** @test */
    public function it_can_guess_title_values()
    {
        $this->assertEquals('title()', $this->typeGuesser->guess('title', 'no-op', 10));
        $this->assertEquals('title()', $this->typeGuesser->guess('title', 'no-op'));
    }

    /** @test */
    public function it_can_guess_sentence_values()
    {
        $this->assertEquals('sentence()', $this->typeGuesser->guess('title', 'no-op', 15));
    }

    /** @test */
    public function it_can_guess_password_values()
    {
        $this->assertEquals('password()', $this->typeGuesser->guess('password', 'no-op'));
    }

    /** @test */
    public function it_can_guess_coordinates_based_on_their_names()
    {
        $this->assertEquals('latitude()', $this->typeGuesser->guess('latitude', 'no-op'));
        $this->assertEquals('longitude()', $this->typeGuesser->guess('longitude', 'no-op'));
    }

    /** @test */
    public function it_returns_word_as_default_value()
    {
        $this->assertEquals('word()', $this->typeGuesser->guess('not_guessable', 'no-op'));
    }
}
