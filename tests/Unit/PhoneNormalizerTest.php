<?php

namespace Tests\Unit;

use App\Services\PhoneNormalizer;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNormalizerTest extends TestCase
{
    #[DataProvider('validPhones')]
    public function test_mexican_formats_share_an_idempotent_canonical_value(string $original, string $canonical): void
    {
        $normalizer = new PhoneNormalizer;
        $this->assertSame($canonical, $normalizer->normalize($original, 'MX'));
        $this->assertSame($canonical, $normalizer->normalize($canonical, 'MX'));
    }

    /** @return list<array{string, string}> */
    public static function validPhones(): array
    {
        return [
            ['+52 614 123 4567', '+526141234567'],
            ['+526141234567', '+526141234567'],
            ['6141234567', '+526141234567'],
            ['614 123 4567', '+526141234567'],
            ['(614) 123-4567', '+526141234567'],
            [' +52 (614) 123-4567 ', '+526141234567'],
            ['+52 55 1234 5678', '+525512345678'],
            ['(33) 1234-5678', '+523312345678'],
            ['81-1234-5678', '+528112345678'],
        ];
    }

    public function test_country_is_explicit_and_normalized_without_guessing_from_phone(): void
    {
        $this->assertSame('+526141234567', (new PhoneNormalizer)->normalize('6141234567', ' mx '));
    }

    #[DataProvider('invalidPhones')]
    public function test_invalid_or_ambiguous_input_has_generic_phone_validation_error(string $input): void
    {
        try {
            (new PhoneNormalizer)->normalize($input, 'MX');
            $this->fail('La entrada inválida fue aceptada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('phone', $exception->errors());
            $this->assertSame('Ingresa un teléfono de México con 10 dígitos, o +52 seguido de 10 dígitos.', $exception->errors()['phone'][0]);
        }
    }

    /** @return list<array{string}> */
    public static function invalidPhones(): array
    {
        return [
            [''], [' '], ['1234567'], ['12345678'], ['614123456'], ['61412345678'],
            ['+5216141234567'], ['0446141234567'], ['0456141234567'], ['016141234567'],
            ['00526141234567'], ['526141234567'], ['+16141234567'], ['+34614123456'],
            ['0000000000'], ['1234567890'], ['+520000000000'],
            ['+52 614 123 4567 ext 9'], ['6141234567x9'], ['6141234567#9'],
            ['++526141234567'], ['614+1234567'], ['(6141234567'], ['614)1234567'],
            ['(61)(4)1234567'], ['+52 (614 123) 4567'], ['614.123.4567'],
            ["614\n1234567"], ["614\t1234567"], ["6141234567\r\n"], ["614123\0".'4567'],
            ['+52 ٦١٤١٢٣٤٥٦٧'], ['+52 ６１４１２３４５６７'], ["614\u{200B}1234567"],
            [str_repeat(' ', 65).'6141234567'],
        ];
    }

    #[DataProvider('unsupportedCountries')]
    public function test_unsupported_or_missing_country_is_not_inferred(string $country): void
    {
        try {
            (new PhoneNormalizer)->normalize('+526141234567', $country);
            $this->fail('El país no admitido fue aceptado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('country', $exception->errors());
        }
    }

    /** @return list<array{string}> */
    public static function unsupportedCountries(): array
    {
        return [[''], ['US'], ['ES'], ['52'], ['MEX'], ['MX-US'], ["MX\n"]];
    }
}
