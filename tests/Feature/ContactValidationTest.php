<?php

namespace Tests\Feature;

use App\Data\ContactData;
use App\Http\Requests\StartCheckoutRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContactValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        Route::middleware('web')->post('/_tests/contact', function (StartCheckoutRequest $request): JsonResponse {
            $contact = $request->contactData();

            return response()->json([
                'name' => $contact->name,
                'email' => $contact->email,
                'phone_original' => $contact->phoneOriginal,
                'phone_canonical' => $contact->phoneCanonical,
                'validated_fields' => array_keys($request->validated()),
            ]);
        });
        Route::middleware('web')->get('/_tests/contact', fn (): Response => response('Formulario de prueba'));
    }

    /** @return array{name:string,email:string,phone:string,country:string} */
    private function contactInput(): array
    {
        return ['name' => 'Persona de prueba', 'email' => 'cliente@example.test', 'phone' => '+52 614 123 4567', 'country' => 'MX'];
    }

    public function test_guest_contact_is_validated_preserving_original_and_computing_canonical_on_server(): void
    {
        $input = array_replace($this->contactInput(), ['name' => '  María López  ', 'email' => '  CLIENTE@example.test  ', 'phone' => ' +52 (614) 123-4567 ', 'country' => ' mx ']);
        $this->postJson('/_tests/contact', $input)->assertOk()->assertJsonPath('name', 'María López')
            ->assertJsonPath('email', 'cliente@example.test')->assertJsonPath('phone_original', $input['phone'])
            ->assertJsonPath('phone_canonical', '+526141234567');
        $this->assertGuest();
    }

    public function test_canonical_roles_and_prices_submitted_by_browser_are_ignored(): void
    {
        $input = $this->contactInput() + ['phone_canonical' => '+525555555555', 'phone_original' => 'falso', 'role' => 'administrator', 'price' => 1, 'user_id' => 1];
        $this->postJson('/_tests/contact', $input)->assertOk()->assertJsonPath('phone_canonical', '+526141234567')
            ->assertJsonPath('phone_original', $input['phone'])->assertJsonPath('validated_fields', ['name', 'email', 'phone', 'country']);
        $this->assertGuest();
        $this->get('/admin')->assertRedirect('/login');
    }

    #[DataProvider('invalidContactFields')]
    public function test_missing_malformed_and_overlong_contact_fields_are_rejected(string $field, mixed $value): void
    {
        $input = array_replace($this->contactInput(), [$field => $value]);
        $this->postJson('/_tests/contact', $input)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    /** @return list<array{string, mixed}> */
    public static function invalidContactFields(): array
    {
        return [
            ['name', null], ['name', '   '], ['name', ['bad']], ['name', 123], ['name', str_repeat('á', 151)], ['name', "Persona\nOtra"], ['name', "Persona\0Otra"],
            ['email', null], ['email', 'sin-correo'], ['email', ['bad']], ['email', str_repeat('a', 256).'@example.test'], ['email', "a@example.test\r\nBcc: otra@example.test"],
            ['phone', null], ['phone', 6141234567], ['phone', ['bad']], ['phone', true], ['phone', '+5216141234567'], ['phone', '1234567'], ['phone', str_repeat('6', 65)], ['phone', "+526141234567\n"],
            ['country', null], ['country', ''], ['country', ['MX']], ['country', 'US'], ['country', 'MEX'],
        ];
    }

    public function test_explicit_country_is_required_even_for_international_input(): void
    {
        $input = $this->contactInput();
        unset($input['country']);
        $this->postJson('/_tests/contact', $input)->assertUnprocessable()->assertJsonValidationErrors('country');
    }

    public function test_request_rejects_invalid_phone_even_when_controller_does_not_build_dto(): void
    {
        Route::middleware('web')->post('/_tests/contact-validation', fn (StartCheckoutRequest $request): Response => response()->noContent());
        $this->postJson('/_tests/contact-validation', array_replace($this->contactInput(), ['phone' => '+5216141234567']))
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    #[DataProvider('invalidUtf8Fields')]
    public function test_form_encoded_invalid_utf8_is_rejected_before_constructing_contact(string $field): void
    {
        $input = array_replace($this->contactInput(), [$field => "Persona\xC3\x28"]);
        $this->post('/_tests/contact', $input, ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    /** @return list<array{string}> */
    public static function invalidUtf8Fields(): array
    {
        return [['name'], ['email'], ['phone'], ['country']];
    }

    public function test_invalid_utf8_does_not_corrupt_json_session_when_flashing_html_input(): void
    {
        $input = array_replace($this->contactInput(), ['phone' => "614\xC3\x28".'1234567']);
        $this->withSession(['cart.lines' => [['product_id' => 1, 'quantity' => 3]]])->from('/_tests/contact')->post('/_tests/contact', $input)
            ->assertRedirect('/_tests/contact')->assertSessionHasErrors('phone')
            ->assertSessionHas('_old_input', fn (array $oldInput): bool => json_encode($oldInput) !== false)
            ->assertSessionHas('cart.lines', [['product_id' => 1, 'quantity' => 3]]);
    }

    public function test_optional_street_and_account_are_not_required(): void
    {
        $this->postJson('/_tests/contact', $this->contactInput())->assertOk();
        $this->assertGuest();
    }

    public function test_contact_does_not_change_authenticated_identity(): void
    {
        $user = User::factory()->installer()->create();
        $this->actingAs($user)->postJson('/_tests/contact', $this->contactInput() + ['user_id' => 999, 'role' => 'administrator'])->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->fresh()->isAdministrator());
    }

    public function test_html_validation_has_generic_errors_and_preserves_original_input(): void
    {
        $this->from('/_tests/contact')->post('/_tests/contact', array_replace($this->contactInput(), ['phone' => '+5216141234567']))
            ->assertRedirect('/_tests/contact')->assertSessionHasErrors('phone')
            ->assertSessionHasInput('phone', '+5216141234567');
    }

    public function test_contact_dto_is_immutable_and_has_the_shared_constructor_contract(): void
    {
        $contact = new ContactData('Persona', 'cliente@example.test', '+52 614 123 4567', '+526141234567');
        $this->assertSame('Persona', $contact->name);
        $this->assertSame('cliente@example.test', $contact->email);
        $this->assertSame('+52 614 123 4567', $contact->phoneOriginal);
        $this->assertSame('+526141234567', $contact->phoneCanonical);
        $this->assertTrue((new \ReflectionClass($contact))->isReadOnly());
    }

    public function test_phone_does_not_expose_an_order_search_route(): void
    {
        $this->get('/pedidos?phone=+526141234567')->assertNotFound();
    }
}
