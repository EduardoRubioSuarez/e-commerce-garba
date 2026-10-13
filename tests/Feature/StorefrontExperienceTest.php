<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StorefrontExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'app.debug' => false]);
        StoreSetting::factory()->create();
    }

    public function test_product_cart_and_checkout_keep_the_purchase_context(): void
    {
        $product = Product::factory()->published()->create();
        $this->get('/productos/'.$product->slug)->assertOk()->assertSee('Ver carrito');
        $this->withSession(['cart.lines' => [['product_id' => $product->id, 'quantity' => 2]]])
            ->get('/carrito')->assertOk()->assertSee('Progreso de compra')->assertSee('aria-current="step"', false);
        $this->get('/checkout')->assertOk()->assertSee('Progreso de compra')->assertSee('Datos de contacto')
            ->assertSee('data-submit-feedback', false)->assertSee('name="country"', false);
    }

    public function test_cart_actions_have_specific_feedback_without_changing_quantity_semantics(): void
    {
        $product = Product::factory()->published()->create();
        $url = route('cart.update');
        $this->put($url, ['product_id' => $product->id, 'quantity' => 2])->assertSessionHas('cart_notice', 'Equipo agregado al carrito.');
        $this->put($url, ['product_id' => $product->id, 'quantity' => 3])->assertSessionHas('cart_notice', 'Cantidad actualizada.');
        $this->get('/carrito')->assertSee('Cantidad actualizada.')->assertSee('data-notice', false);
        $this->put($url, ['product_id' => $product->id, 'action' => 'remove'])->assertSessionHas('cart_notice', 'Equipo eliminado del carrito.');
        $this->getJson('/carrito')->assertJsonCount(0, 'data.lines');
    }

    public function test_invalid_contact_is_preserved_and_errors_are_connected_to_fields(): void
    {
        $product = Product::factory()->published()->create();
        $this->withSession(['cart.lines' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->from('/checkout')->post(route('checkout.review'), [
                'name' => 'Persona de prueba', 'email' => 'invalid-email', 'phone' => '123', 'country' => 'MX',
            ])->assertRedirect('/checkout');
        $response = $this->get('/checkout');
        $response->assertOk()->assertSee('Persona de prueba')->assertSee('invalid-email')
            ->assertSee('aria-describedby="field-email-error"', false)->assertSee('id="field-email-error"', false)
            ->assertSee('aria-describedby="ayuda-telefono field-phone-error"', false)
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_quantity_error_only_marks_the_submitted_cart_item(): void
    {
        $first = Product::factory()->published()->create();
        $second = Product::factory()->published()->create();
        $this->withSession(['cart.lines' => [
            ['product_id' => $first->id, 'quantity' => 1], ['product_id' => $second->id, 'quantity' => 2],
        ]])->from('/carrito')->put(route('cart.update'), ['product_id' => $first->id, 'quantity' => 0])
            ->assertRedirect('/carrito');
        $this->get('/carrito')->assertOk()->assertSee('id="cantidad-error-'.$first->id.'"', false)
            ->assertDontSee('id="cantidad-error-'.$second->id.'"', false)
            ->assertSee('aria-describedby="cantidad-error-'.$first->id.'"', false)
            ->assertDontSee('aria-describedby="cantidad-error-'.$second->id.'"', false);
        $this->getJson('/carrito')->assertJsonPath('data.lines.0.quantity', 1)->assertJsonPath('data.lines.1.quantity', 2);
    }

    #[DataProvider('errorStates')]
    public function test_error_pages_preserve_status_and_offer_a_safe_way_out(int $status, string $title): void
    {
        Route::get('/experience-error/'.$status, fn () => abort($status, 'Internal detail must stay private'));
        $this->get('/experience-error/'.$status)->assertStatus($status)->assertSee($title)
            ->assertSee('Volver al catálogo')->assertDontSee('Internal detail must stay private');
        $this->getJson('/experience-error/'.$status)->assertStatus($status)->assertHeader('Content-Type', 'application/json');
    }

    /** @return list<array{int, string}> */
    public static function errorStates(): array
    {
        return [[403, 'No tienes acceso a esta página'], [404, 'No encontramos esta página'],
            [419, 'Tu sesión venció'], [429, 'Necesitamos una pausa'],
            [500, 'No pudimos cargar esta página'], [503, 'Estamos temporalmente fuera de servicio']];
    }
}
