<?php

namespace Tests\Feature;

use App\Enums\PriceTier;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\PriceOverride;
use App\Models\Product;
use App\Models\ProductPhoto;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        StoreSetting::factory()->create();
    }

    /** @param array<string, mixed> $attributes */
    private function publishedProduct(array $attributes = []): Product
    {
        return Product::factory()->published()->create(array_merge(['name' => 'Equipo visible', 'base_cost_cents' => 100000], $attributes));
    }

    private function productUrl(Product $product): string
    {
        return '/productos/'.$product->slug;
    }

    public function test_catalog_and_home_only_render_active_published_products(): void
    {
        $visible = $this->publishedProduct();
        Product::factory()->create(['name' => 'Borrador secreto']);
        Product::factory()->published()->inactive()->create(['name' => 'Equipo retirado']);
        foreach (['/', '/catalogo'] as $url) {
            $this->get($url)->assertOk()->assertSee($visible->name)->assertDontSee('Borrador secreto')->assertDontSee('Equipo retirado')->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->getJson('/catalogo')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_product_details_reject_drafts_inactive_and_unknown_slugs(): void
    {
        $this->get($this->productUrl($this->publishedProduct()))->assertOk();
        $draft = Product::factory()->create();
        $inactive = Product::factory()->published()->inactive()->create();
        foreach ([$this->productUrl($draft), $this->productUrl($inactive), '/productos/no-existe'] as $url) {
            $this->get($url)->assertNotFound();
            $this->getJson($url)->assertNotFound();
        }
    }

    public function test_guest_prices_ignore_browser_tier_role_and_amount(): void
    {
        $product = $this->publishedProduct();
        $query = '?tier=installer&role=installer&price=1';
        $this->getJson('/catalogo'.$query)->assertOk()->assertJsonPath('data.0.price.total_cents', 150800)->assertJsonPath('data.0.price.currency', 'MXN');
        $this->getJson($this->productUrl($product).$query)->assertOk()->assertJsonPath('data.price.total_cents', 150800);
        $this->get($this->productUrl($product).$query)->assertOk()->assertSee('$1,508.00')->assertDontSee('$1,276.00');
    }

    #[DataProvider('accountStates')]
    public function test_price_tier_requires_current_active_installer(string $state, int $expected): void
    {
        $product = $this->publishedProduct();
        $factory = User::factory();
        $user = match ($state) {
            'installer' => $factory->installer()->create(),
            'administrator' => $factory->administrator()->create(),
            'inactive' => $factory->inactive()->create(),
            'suspended' => $factory->installer()->suspended()->create(),
            'unactivated' => $factory->installer()->create(['activated_at' => null]),
        };
        $this->actingAs($user);
        $this->getJson('/catalogo')->assertOk()->assertJsonPath('data.0.price.total_cents', $expected)->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson($this->productUrl($product))->assertOk()->assertJsonPath('data.price.total_cents', $expected)->assertHeader('Cache-Control', 'no-store, private');
    }

    /** @return list<array{string, int}> */
    public static function accountStates(): array
    {
        return [['installer', 127600], ['administrator', 150800], ['inactive', 150800], ['suspended', 150800], ['unactivated', 150800]];
    }

    #[DataProvider('revocations')]
    public function test_old_session_cannot_keep_revoked_installer_price(string $mutation): void
    {
        $product = $this->publishedProduct();
        $user = User::factory()->installer()->create();
        $this->actingAs($user);
        $this->getJson($this->productUrl($product))->assertJsonPath('data.price.total_cents', 127600);
        if ($mutation === 'delete') {
            User::query()->whereKey($user->id)->delete();
        } else {
            User::query()->whereKey($user->id)->update(match ($mutation) {
                'suspend' => ['suspended_at' => now()],
                'deactivate' => ['is_active' => false],
                'role' => ['role' => UserRole::Administrator->value],
            });
        }
        $this->getJson('/catalogo')->assertJsonPath('data.0.price.total_cents', 150800);
        $this->getJson($this->productUrl($product))->assertJsonPath('data.price.total_cents', 150800);
    }

    /** @return list<array{string}> */
    public static function revocations(): array
    {
        return [['suspend'], ['deactivate'], ['role'], ['delete']];
    }

    public function test_logout_returns_to_public_price(): void
    {
        $product = $this->publishedProduct();
        $this->actingAs(User::factory()->installer()->create());
        $this->getJson($this->productUrl($product))->assertJsonPath('data.price.total_cents', 127600);
        $this->post('/logout')->assertRedirect('/login');
        $this->getJson($this->productUrl($product))->assertJsonPath('data.price.total_cents', 150800);
    }

    public function test_each_audience_receives_only_its_price_and_no_internal_values(): void
    {
        $product = $this->publishedProduct(['base_cost_cents' => 314159]);
        PriceOverride::factory()->create(['product_id' => $product->id, 'tier' => PriceTier::Public, 'base_price_cents' => 400000]);
        PriceOverride::factory()->create(['product_id' => $product->id, 'tier' => PriceTier::Installer, 'base_price_cents' => 765432]);
        foreach (['/catalogo', $this->productUrl($product)] as $url) {
            foreach ([$this->get($url)->assertOk()->getContent(), $this->getJson($url)->assertOk()->getContent()] as $content) {
                foreach (['base_cost', 'priceOverrides', 'price_overrides', '314159', '3141.59', '765432', '7654.32', '8879.01'] as $privateValue) {
                    $this->assertStringNotContainsString($privateValue, $content);
                }
            }
        }
        $this->actingAs(User::factory()->installer()->create());
        foreach (['/catalogo', $this->productUrl($product)] as $url) {
            foreach ([$this->get($url)->assertOk()->getContent(), $this->getJson($url)->assertOk()->getContent()] as $content) {
                foreach (['base_cost', 'priceOverrides', 'price_overrides', '314159', '3141.59', '400000', '4000.00', '4640.00'] as $privateValue) {
                    $this->assertStringNotContainsString($privateValue, $content);
                }
            }
        }
    }

    public function test_search_and_category_filters_are_combined_and_bound(): void
    {
        $category = Category::factory()->create(['slug' => 'minisplit']);
        $target = $this->publishedProduct(['name' => 'Inverter elegido', 'category_id' => $category->id]);
        $this->publishedProduct(['name' => 'Inverter otra categoría']);
        $this->publishedProduct(['name' => 'Ventilador', 'description' => 'Sin coincidencias', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Inverter borrador', 'category_id' => $category->id]);
        $this->getJson('/catalogo?q=Inverter&category=minisplit')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);
        $this->getJson('/catalogo?q='.urlencode("' OR 1=1 --"))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/catalogo?category=inexistente')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_search_treats_wildcard_characters_literally(): void
    {
        $target = $this->publishedProduct(['name' => 'Equipo 50%_!', 'description' => 'Texto']);
        $this->publishedProduct(['name' => 'Otro equipo', 'description' => 'Texto']);
        $this->getJson('/catalogo?q='.urlencode('%_!'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);
    }

    public function test_category_navigation_does_not_reveal_hidden_catalog(): void
    {
        $visible = Category::factory()->create(['name' => 'Categoría visible']);
        $hidden = Category::factory()->create(['name' => 'Categoría interna']);
        $this->publishedProduct(['category_id' => $visible->id]);
        Product::factory()->create(['category_id' => $hidden->id]);
        $this->get('/catalogo')->assertOk()->assertSee('Categoría visible')->assertDontSee('Categoría interna');
    }

    public function test_pagination_is_stable_and_preserves_only_valid_filters(): void
    {
        $category = Category::factory()->create(['slug' => 'equipos']);
        $products = Product::factory()->published()->count(21)->create(['name' => 'Equipo', 'category_id' => $category->id]);
        $this->getJson('/catalogo?q=Equipo&category=equipos&tier=installer')->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 21)->assertJsonPath('meta.last_page', 2);
        $this->getJson('/catalogo?q=Equipo&category=equipos&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $products->last()->id);
        $this->get('/catalogo?q=Equipo&category=equipos&tier=installer')->assertOk()->assertSee('page=2', false)->assertSee('category=equipos', false)->assertDontSee('tier=installer', false);
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filter_shapes_are_rejected(string $query, string $field): void
    {
        $this->getJson('/catalogo?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    /** @return list<array{string, string}> */
    public static function invalidFilters(): array
    {
        return [['q[]=x', 'q'], ['category[]=x', 'category'], ['q='.str_repeat('x', 121), 'q'], ['page=0', 'page'], ['page=-1', 'page'], ['page=abc', 'page'], ['page[]=1', 'page']];
    }

    public function test_product_text_and_photos_are_escaped_and_ordered(): void
    {
        $product = $this->publishedProduct(['name' => '<script>name</script>', 'description' => '<script>description</script>', 'specifications' => '<b>specifications</b>', 'equipment_contents' => '<img src=x onerror=alert(1)>']);
        ProductPhoto::factory()->create(['product_id' => $product->id, 'path' => 'product-photos/second.png', 'alt' => 'Foto secundaria', 'sort_order' => 2]);
        ProductPhoto::factory()->create(['product_id' => $product->id, 'path' => 'product-photos/first.png', 'alt' => '<script>alt</script>', 'sort_order' => 1]);
        $this->get($this->productUrl($product))->assertOk()->assertSee($product->name)->assertSee($product->description)->assertSee($product->specifications)->assertSee($product->equipment_contents)->assertDontSee('<script>name</script>', false)->assertDontSee('<img src=x', false)->assertSeeInOrder(['product-photos/first.png', 'product-photos/second.png'], false);
        $this->getJson($this->productUrl($product))->assertOk()->assertJsonPath('data.photos.0.alt', '<script>alt</script>')->assertJsonPath('data.photos.1.alt', 'Foto secundaria');
        $this->get('/catalogo')->assertOk()->assertSee('product-photos/first.png', false)->assertDontSee('product-photos/second.png', false);
    }

    public function test_effective_manual_price_is_requoted_after_changes(): void
    {
        $product = $this->publishedProduct();
        $override = PriceOverride::factory()->create(['product_id' => $product->id, 'tier' => PriceTier::Public, 'base_price_cents' => 200000]);
        $this->getJson($this->productUrl($product))->assertJsonPath('data.price.total_cents', 232000);
        $override->update(['base_price_cents' => 300000]);
        $this->getJson($this->productUrl($product))->assertJsonPath('data.price.total_cents', 348000);
    }

    public function test_corrupt_price_does_not_publish_zero_or_internal_error(): void
    {
        $product = $this->publishedProduct();
        PriceOverride::factory()->create(['product_id' => $product->id, 'tier' => PriceTier::Public, 'base_price_cents' => 0]);
        $this->getJson($this->productUrl($product))->assertOk()->assertJsonPath('data.price', null);
        $this->get($this->productUrl($product))->assertOk()->assertSee('Precio no disponible')->assertDontSee('$0.00')->assertDontSee('effective unit price');
    }

    public function test_empty_store_has_clear_state_and_no_purchase_placeholder(): void
    {
        $this->get('/catalogo')->assertOk()->assertSee('No hay productos')->assertDontSee('name="coupon"', false)->assertDontSee('Agregar al carrito');
        $this->getJson('/catalogo')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    }

    public function test_catalog_database_queries_do_not_grow_per_product(): void
    {
        Product::factory()->published()->count(20)->create();
        $connection = Product::resolveConnection();
        $connection->enableQueryLog();
        $this->getJson('/catalogo')->assertOk()->assertJsonCount(20, 'data');
        $selects = array_filter($connection->getQueryLog(), fn (array $query): bool => str_starts_with(strtolower($query['query']), 'select'));
        $connection->disableQueryLog();
        $this->assertLessThanOrEqual(9, count($selects), 'El catálogo debe cargar relaciones y precios por lotes.');
    }

    public function test_json_contains_an_explicit_single_price_projection(): void
    {
        $product = $this->publishedProduct();
        $response = $this->getJson($this->productUrl($product))->assertOk();
        $this->assertSame(['total_cents', 'currency'], array_keys($response->json('data.price')));
        foreach (['base_cost_cents', 'price_overrides', 'is_published'] as $privateField) {
            $this->assertArrayNotHasKey($privateField, $response->json('data'));
        }
    }
}
