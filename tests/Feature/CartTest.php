<?php

namespace Tests\Feature;

use App\Enums\PriceTier;
use App\Models\PriceOverride;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        StoreSetting::factory()->create();
    }

    public function test_guest_can_add_replace_remove_and_view_server_priced_cart(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $this->put('/carrito', ['product_id' => $product->id, 'quantity' => '3', 'price' => 1, 'tier' => 'installer'])
            ->assertRedirect('/carrito')->assertSessionHas('cart.lines', [['product_id' => $product->id, 'quantity' => 3]]);
        $this->getJson('/carrito')->assertOk()->assertJsonPath('data.total_cents', 452400)
            ->assertJsonPath('data.lines.0.quantity', 3)->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/carrito')->assertOk()->assertSee('$4,524.00')->assertSee('Solo recogida en local')
            ->assertSee('segundo correo')->assertDontSee('name="coupon"', false);
        $this->put('/carrito', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect('/carrito');
        $this->getJson('/carrito')->assertJsonCount(1, 'data.lines')->assertJsonPath('data.total_cents', 150800);
        $this->put('/carrito', ['product_id' => $product->id, 'action' => 'remove'])->assertRedirect('/carrito')->assertSessionHas('cart.lines', []);
        $this->get('/carrito')->assertOk()->assertSee('Tu carrito está vacío');
    }

    public function test_quick_add_from_catalog_adds_units_instead_of_replacing_quantity(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $this->from('/catalogo')->put('/carrito', ['product_id' => $product->id, 'quantity' => 1, 'action' => 'add', 'return_to' => 'catalog'])
            ->assertRedirect('/catalogo')->assertSessionHas('cart.lines', [['product_id' => $product->id, 'quantity' => 1]])
            ->assertSessionHas('cart_notice', 'Equipo agregado al carrito.');
        $this->putJson('/carrito', ['product_id' => $product->id, 'quantity' => 1, 'action' => 'add'])
            ->assertOk()->assertJsonPath('cart_count', 2)->assertJsonPath('message', 'Equipo agregado al carrito.');
        $this->assertSame([['product_id' => $product->id, 'quantity' => 2]], session('cart.lines'));
        $this->get('/catalogo')->assertOk()->assertSee('data-quick-add', false)->assertSee('Agregar al carrito');
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_quantities_do_not_modify_cart(mixed $quantity): void
    {
        $product = Product::factory()->published()->create();
        $original = [['product_id' => $product->id, 'quantity' => 2]];
        $this->withSession(['cart.lines' => $original])->putJson('/carrito', ['product_id' => $product->id, 'quantity' => $quantity])
            ->assertUnprocessable()->assertHeader('Cache-Control', 'no-store, private')->assertJsonValidationErrors('quantity')->assertSessionHas('cart.lines', $original);
    }

    /** @return list<array{mixed}> */
    public static function invalidQuantities(): array
    {
        return [[0], [-1], [1.5], ['1.5'], ['1e3'], ['9223372036854775808'], [null], [true], [[2]], ['abc']];
    }

    public function test_large_quantity_has_no_inventory_limit_but_money_overflow_is_rejected(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $this->putJson('/carrito', ['product_id' => $product->id, 'quantity' => 100000])->assertOk();
        $this->getJson('/carrito')->assertJsonPath('data.total_cents', 15080000000);
        $original = session('cart.lines');
        $this->putJson('/carrito', ['product_id' => $product->id, 'quantity' => (string) PHP_INT_MAX])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity')->assertSessionHas('cart.lines', $original);
    }

    public function test_overflow_across_products_is_rejected_atomically(): void
    {
        $products = Product::factory()->published()->count(2)->create(['base_cost_cents' => 100000]);
        $quantity = intdiv(PHP_INT_MAX, 150800);
        $this->putJson('/carrito', ['product_id' => $products[0]->id, 'quantity' => $quantity])->assertOk();
        $original = session('cart.lines');
        $this->putJson('/carrito', ['product_id' => $products[1]->id, 'quantity' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity')->assertSessionHas('cart.lines', $original);
    }

    public function test_rejected_update_preserves_session_even_when_an_existing_product_was_withdrawn(): void
    {
        $visible = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $withdrawn = Product::factory()->published()->inactive()->create();
        $original = [['product_id' => $visible->id, 'quantity' => 2], ['product_id' => $withdrawn->id, 'quantity' => 1]];
        $this->withSession(['cart.lines' => $original])->putJson('/carrito', ['product_id' => $visible->id, 'quantity' => (string) PHP_INT_MAX])
            ->assertUnprocessable()->assertSessionHas('cart.lines', $original)->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/carrito')->assertOk()->assertJsonCount(1, 'data.lines')->assertSessionHas('cart_notice');
    }

    public function test_only_visible_products_can_be_added_and_tampered_ids_are_rejected(): void
    {
        $draft = Product::factory()->create();
        $inactive = Product::factory()->published()->inactive()->create();
        foreach ([$draft->id, $inactive->id, 99999, 0, -1, ['id' => 1], '1.2'] as $id) {
            $this->putJson('/carrito', ['product_id' => $id, 'quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors('product_id');
        }
        $this->assertEmpty(session('cart.lines', []));
    }

    public function test_session_prices_are_ignored_and_corrupt_lines_are_cleaned_with_notice(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $this->withSession(['cart.lines' => [
            ['product_id' => $product->id, 'quantity' => 3, 'price' => 1, 'tier' => 'installer'],
            ['product_id' => 99999, 'quantity' => 1], ['product_id' => $product->id, 'quantity' => -1], 'bad',
        ]])->getJson('/carrito')->assertOk()->assertJsonPath('data.total_cents', 452400)
            ->assertSessionHas('cart.lines', [['product_id' => $product->id, 'quantity' => 3]])->assertSessionHas('cart_notice');
    }

    public function test_malformed_session_root_is_treated_as_empty(): void
    {
        $this->withSession(['cart.lines' => 'bad'])->get('/carrito')->assertOk()->assertSee('Tu carrito está vacío')
            ->assertSessionHas('cart.lines', [])->assertSessionHas('cart_notice');
    }

    public function test_deactivated_or_deleted_products_are_removed_before_requoting(): void
    {
        $products = Product::factory()->published()->count(3)->create(['base_cost_cents' => 100000]);
        $items = $products->map(fn (Product $product): array => ['product_id' => $product->id, 'quantity' => 1])->all();
        $products[1]->update(['is_active' => false]);
        $products[2]->delete();
        $this->withSession(['cart.lines' => $items])->getJson('/carrito')->assertOk()->assertJsonCount(1, 'data.lines')
            ->assertJsonPath('data.total_cents', 150800)->assertSessionHas('cart_notice');
    }

    public function test_current_price_settings_and_overrides_are_used_on_every_read(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $this->put('/carrito', ['product_id' => $product->id, 'quantity' => 2]);
        $this->getJson('/carrito')->assertJsonPath('data.total_cents', 301600);
        PriceOverride::factory()->create(['product_id' => $product->id, 'tier' => PriceTier::Public, 'base_price_cents' => 200000]);
        $this->getJson('/carrito')->assertJsonPath('data.total_cents', 464000);
        StoreSetting::current()->update(['tax_basis_points' => 1000]);
        $this->getJson('/carrito')->assertJsonPath('data.total_cents', 440000);
    }

    public function test_login_and_revocation_requote_without_losing_cart(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $user = User::factory()->installer()->create();
        $this->put('/carrito', ['product_id' => $product->id, 'quantity' => 3]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->getJson('/carrito')->assertJsonPath('data.total_cents', 382800);
        User::query()->whereKey($user->id)->update(['is_active' => false]);
        $this->getJson('/carrito')->assertJsonPath('data.total_cents', 452400);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_logout_clears_cart_so_next_account_does_not_inherit_it(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $first = User::factory()->installer()->create();
        $second = User::factory()->installer()->create();
        $this->post('/login', ['email' => $first->email, 'password' => 'password']);
        $this->put('/carrito', ['product_id' => $product->id, 'quantity' => 3]);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertEmpty(session('cart.lines', []));
        $this->post('/login', ['email' => $second->email, 'password' => 'password']);
        $this->getJson('/carrito')->assertJsonCount(0, 'data.lines');
    }

    public function test_cart_does_not_expose_costs_other_prices_or_pricing_internals(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 314159]);
        PriceOverride::factory()->create(['product_id' => $product->id, 'tier' => PriceTier::Installer, 'base_price_cents' => 765432]);
        $this->put('/carrito', ['product_id' => $product->id, 'quantity' => 1]);
        foreach ([$this->get('/carrito')->assertOk()->getContent(), $this->getJson('/carrito')->assertOk()->getContent()] as $content) {
            foreach (['314159', '3141.59', '765432', '7654.32', '8879.01', 'base_cost', 'price_source', 'markup_basis_points'] as $privateValue) {
                $this->assertStringNotContainsString($privateValue, $content);
            }
        }
    }

    public function test_invalid_effective_price_is_not_shown_as_zero_and_can_be_removed(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $this->put('/carrito', ['product_id' => $product->id, 'quantity' => 1]);
        PriceOverride::factory()->create(['product_id' => $product->id, 'tier' => PriceTier::Public, 'base_price_cents' => 0]);
        $this->get('/carrito')->assertOk()->assertSee('No podemos calcular el total')->assertDontSee('effective unit price');
        $this->getJson('/carrito')->assertOk()->assertJsonPath('data.total_cents', null);
        $this->put('/carrito', ['action' => 'remove', 'product_id' => $product->id])->assertRedirect('/carrito');
        $this->get('/carrito')->assertSee('Tu carrito está vacío');
    }

    public function test_product_page_has_real_cart_form_and_named_navigation(): void
    {
        $product = Product::factory()->published()->create();
        $this->get('/productos/'.$product->slug)->assertOk()->assertSee('Agregar al carrito')
            ->assertSee('name="quantity"', false)->assertSee('name="_token"', false)->assertSee('action="'.route('cart.update').'"', false);
        $this->get('/catalogo')->assertOk()->assertSee(route('cart.show'), false)->assertSee(route('store.information'), false);
    }

    public function test_adding_from_product_returns_to_that_product_with_feedback_and_unit_count(): void
    {
        $product = Product::factory()->published()->create();
        $this->put('/carrito', ['product_id' => $product->id, 'quantity' => 3, 'return_to' => 'product'])
            ->assertRedirect(route('store.product', ['slug' => $product->slug]))
            ->assertSessionHas('cart_notice', 'Equipo agregado al carrito.');
        $this->get('/productos/'.$product->slug)->assertOk()->assertSee('Equipo agregado al carrito.')
            ->assertSee('aria-label="Carrito, 3 productos"', false);
        $this->get('/catalogo')->assertOk()->assertSee('aria-label="Carrito, 3 productos"', false);
        $this->put('/carrito', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect('/carrito');
        $this->get('/carrito')->assertOk()->assertSee('aria-label="Carrito, 1 producto"', false);
        $this->put('/carrito', ['product_id' => $product->id, 'action' => 'remove'])->assertRedirect('/carrito');
        $this->get('/catalogo')->assertOk()->assertSee('aria-label="Carrito, 0 productos"', false);
    }

    public function test_header_count_excludes_unavailable_products_and_sums_units(): void
    {
        $products = Product::factory()->published()->count(3)->create();
        $products[2]->update(['is_active' => false]);
        $this->withSession(['cart.lines' => [
            ['product_id' => $products[0]->id, 'quantity' => 2],
            ['product_id' => $products[1]->id, 'quantity' => 3],
            ['product_id' => $products[2]->id, 'quantity' => 4],
        ]])->get('/catalogo')->assertOk()->assertSee('aria-label="Carrito, 5 productos"', false);
    }

    public function test_html_errors_are_clear_and_do_not_change_existing_cart(): void
    {
        $product = Product::factory()->published()->create();
        $this->from('/productos/'.$product->slug)->put('/carrito', ['product_id' => $product->id, 'quantity' => 0])
            ->assertRedirect('/productos/'.$product->slug)->assertSessionHasErrors('quantity')->assertHeader('Cache-Control', 'no-store, private');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get('/productos/'.$product->slug)->assertSee('La cantidad debe ser un entero positivo');
    }

    public function test_csrf_protects_guest_cart_mutations(): void
    {
        $product = Product::factory()->published()->create();
        $this->app['env'] = 'local';
        try {
            $this->put('/carrito', ['product_id' => $product->id, 'quantity' => 1])->assertStatus(419);
            $this->withSession(['_token' => 'test-token'])->put('/carrito', ['_token' => 'test-token', 'product_id' => $product->id, 'quantity' => 1])->assertRedirect('/carrito');
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_cart_service_contract_and_removal_are_available_to_checkout(): void
    {
        $product = Product::factory()->published()->create();
        $cart = $this->app->make(CartService::class);
        $cart->put($product->id, 2);
        $this->assertSame($product->id, $cart->lines()[0]->productId);
        $this->assertSame(2, $cart->lines()[0]->quantity);
        $cart->remove($product->id);
        $this->assertSame([], $cart->lines());
    }

    public function test_information_is_configurable_escaped_and_explicit_about_pickup(): void
    {
        StoreSetting::current()->update(['pickup_name' => 'Local de prueba', 'pickup_address' => 'Calle de prueba', 'pickup_hours' => 'Horario de prueba', 'contact_email' => 'contacto@example.test', 'contact_phone' => '+526141234567']);
        config(['store.information.terms' => '<script>condiciones</script>', 'store.information.privacy' => 'Privacidad de prueba', 'store.information.returns' => 'Devoluciones de prueba']);
        $this->get('/informacion')->assertOk()->assertDontSee('Local de prueba')->assertSee('Calle de prueba')->assertSee('Horario de prueba')
            ->assertSee('contacto@example.test')->assertSee('+526141234567')->assertSee('<script>condiciones</script>')
            ->assertDontSee('<script>condiciones</script>', false)->assertSee('Privacidad de prueba')->assertDontSee('Devoluciones de prueba')
            ->assertDontSee('Contenido de demostración')->assertSee('segundo correo')->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/informacion')->assertOk()->assertJsonMissingPath('data.pickup.name')->assertJsonMissingPath('data.public_markup_basis_points');
    }
}
