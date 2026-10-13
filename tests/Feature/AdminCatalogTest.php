<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
    }

    /** @return array<string, mixed> */
    private function productData(Category $category, array $changes = []): array
    {
        return array_replace([
            'category_id' => $category->id,
            'name' => 'Equipo de prueba',
            'slug' => 'equipo-prueba',
            'description' => 'Descripción de prueba',
            'specifications' => 'Capacidad: 12000 BTU',
            'equipment_contents' => 'Unidad interior y exterior',
            'base_cost' => '1000.01',
            'is_published' => false,
            'is_active' => true,
        ], $changes);
    }

    public function test_guests_cannot_read_or_write_catalog(): void
    {
        foreach (['/admin/products', '/admin/products/create', '/admin/categories'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/admin/products', [])->assertRedirect('/login');
        $this->post('/admin/categories', [])->assertRedirect('/login');
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_installers_and_suspended_administrators_cannot_mutate_catalog(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        foreach ([User::factory()->installer()->create(), User::factory()->administrator()->suspended()->create()] as $user) {
            foreach (['/admin/products', '/admin/products/create', '/admin/products/'.$product->id.'/edit', '/admin/categories'] as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
            $this->actingAs($user)->post('/admin/products', $this->productData($category))->assertForbidden();
            $this->actingAs($user)->put('/admin/products/'.$product->id, $this->productData($category))->assertForbidden();
            $this->actingAs($user)->post('/admin/categories', ['name' => 'Intruso', 'slug' => 'intruso'])->assertForbidden();
            $this->actingAs($user)->put('/admin/categories/'.$category->id, ['name' => 'Intruso', 'slug' => 'intruso'])->assertForbidden();
        }
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('categories', 1);
        $this->assertNotSame('Intruso', $category->fresh()->name);
        $this->assertNotSame('Equipo de prueba', $product->fresh()->name);
    }

    public function test_administrator_creates_and_edits_categories_with_unique_slugs(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $this->post('/admin/categories', ['name' => 'Minisplit', 'slug' => 'minisplit'])->assertRedirect('/admin/categories');
        $category = Category::sole();
        $this->put('/admin/categories/'.$category->id, ['name' => 'Equipos minisplit', 'slug' => 'minisplit'])->assertRedirect('/admin/categories');
        $this->assertSame('Equipos minisplit', $category->fresh()->name);
        $this->post('/admin/categories', ['name' => 'Otro', 'slug' => 'minisplit'])->assertSessionHasErrors('slug');
        $other = Category::factory()->create();
        $this->put('/admin/categories/'.$other->id, ['name' => 'Otro', 'slug' => 'minisplit', 'id' => $category->id])->assertSessionHasErrors('slug');
        $this->post('/admin/categories', ['name' => 'Otro', 'slug' => 'BAD slug'])->assertSessionHasErrors('slug');
        $this->assertDatabaseCount('categories', 2);
    }

    public function test_administrator_creates_product_with_exact_cost_and_safe_defaults(): void
    {
        $category = Category::factory()->create();
        $this->actingAs(User::factory()->administrator()->create())
            ->post('/admin/products', $this->productData($category, ['base_cost_cents' => 1, 'price' => 2]))
            ->assertRedirect('/admin/products');
        $product = Product::sole();
        $this->assertSame(100001, $product->base_cost_cents);
        $this->assertFalse($product->is_published);
        $this->assertTrue($product->is_active);
        $this->assertSame($category->id, $product->category->id);
        $this->assertArrayNotHasKey('base_cost_cents', $product->toArray());
        $this->assertSame($product->id, $category->products()->sole()->id);
    }

    public function test_edit_publish_and_deactivate_preserve_product_identity_and_reference(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $this->actingAs(User::factory()->administrator()->create());
        $data = $this->productData($category, ['slug' => $product->slug, 'is_published' => true]);
        $this->put('/admin/products/'.$product->id, $data)->assertRedirect('/admin/products');
        $this->assertTrue($product->fresh()->is_published);
        $data['is_active'] = false;
        $data['base_cost'] = '2000.50';
        $this->put('/admin/products/'.$product->id, $data)->assertRedirect('/admin/products');
        $product->refresh();
        $this->assertFalse($product->is_active);
        $this->assertSame(200050, $product->base_cost_cents);
        $this->assertSame($category->id, $product->category_id);
        $this->assertDatabaseCount('products', 1);
        $this->delete('/admin/products/'.$product->id)->assertStatus(405);
        $this->delete('/admin/categories/'.$category->id)->assertStatus(405);
    }

    #[DataProvider('validCosts')]
    public function test_money_is_converted_exactly(string $cost, int $cents): void
    {
        $category = Category::factory()->create();
        $this->actingAs(User::factory()->administrator()->create())->post('/admin/products', $this->productData($category, ['base_cost' => $cost]))->assertRedirect('/admin/products');
        $this->assertSame($cents, Product::sole()->base_cost_cents);
    }

    /** @return array<string, array{string, int}> */
    public static function validCosts(): array
    {
        return ['whole' => ['10', 1000], 'one decimal' => ['10.5', 1050], 'cent' => ['0.01', 1], 'upper boundary' => ['9999999999.99', 999999999999]];
    }

    #[DataProvider('invalidCosts')]
    public function test_invalid_money_does_not_create_or_modify_product(string $cost): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'base_cost_cents' => 555]);
        $this->actingAs(User::factory()->administrator()->create());
        $data = $this->productData($category, ['base_cost' => $cost]);
        $this->post('/admin/products', $data)->assertSessionHasErrors('base_cost');
        $this->put('/admin/products/'.$product->id, $data)->assertSessionHasErrors('base_cost');
        $this->assertDatabaseCount('products', 1);
        $this->assertSame(555, $product->fresh()->base_cost_cents);
    }

    /** @return array<string, array{string}> */
    public static function invalidCosts(): array
    {
        return ['negative' => ['-1'], 'zero' => ['0.00'], 'fractional cent' => ['1.001'], 'exponent' => ['1e3'], 'comma' => ['1,20'], 'overflow' => ['10000000000.00'], 'malformed' => ['abc']];
    }

    public function test_invalid_category_and_duplicate_product_slug_are_rejected(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->inactive()->create(['category_id' => $category->id, 'slug' => 'reserved-slug']);
        $other = Product::factory()->create(['category_id' => $category->id]);
        $this->actingAs(User::factory()->administrator()->create());
        $this->post('/admin/products', $this->productData($category, ['category_id' => 999999]))->assertSessionHasErrors('category_id');
        $this->post('/admin/products', $this->productData($category, ['slug' => $product->slug]))->assertSessionHasErrors('slug');
        $this->put('/admin/products/'.$other->id, $this->productData($category, ['slug' => $product->slug, 'id' => $product->id]))->assertSessionHasErrors('slug');
        $this->post('/admin/products', $this->productData($category, ['slug' => 'BAD slug']))->assertSessionHasErrors('slug');
        $this->assertDatabaseCount('products', 2);
    }

    public function test_views_escape_content_and_have_forms_pagination_and_private_cache(): void
    {
        $category = Category::factory()->create(['name' => '<script>alert(1)</script>']);
        $product = Product::factory()->create(['category_id' => $category->id, 'name' => '<script>alert(2)</script>', 'description' => '<script>alert(3)</script>', 'base_cost_cents' => 100001]);
        $this->actingAs(User::factory()->administrator()->create());
        foreach (['/admin/products', '/admin/products/create', '/admin/products/'.$product->id.'/edit', '/admin/categories'] as $url) {
            $response = $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $response->assertDontSee('<script>alert(', false);
        }
        $this->get('/admin/products/'.$product->id.'/edit')->assertSee('&lt;script&gt;alert(3)&lt;/script&gt;', false)->assertSee('1000.01')->assertSee('name="_token"', false)->assertSee('name="_method"', false);
        Product::factory()->count(21)->create(['category_id' => $category->id]);
        $this->get('/admin/products')->assertViewHas('products', fn ($products): bool => $products->count() === 20 && $products->total() === 22);
        $this->get('/admin/products?page=2')->assertViewHas('products', fn ($products): bool => $products->count() === 2);
        $this->get('/admin')->assertSee(route('admin.products.index'))->assertSee(route('admin.categories.index'));
    }

    public function test_invalid_form_keeps_unchecked_state_and_previous_cost_input(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->published()->create(['category_id' => $category->id, 'is_active' => true]);
        $this->actingAs(User::factory()->administrator()->create())
            ->from('/admin/products/'.$product->id.'/edit')
            ->put('/admin/products/'.$product->id, $this->productData($category, ['name' => '', 'base_cost' => '42.50', 'is_active' => '0', 'is_published' => '0']))
            ->assertSessionHasErrors('name');
        $response = $this->withCookie(config('session.cookie'), session()->getId())
            ->get('/admin/products/'.$product->id.'/edit')->assertOk()->assertSee('42.50')->assertSee('Ingresa el nombre del producto.');
        $this->assertDoesNotMatchRegularExpression('/type="checkbox"[^>]*name="is_active"[^>]*checked/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/type="checkbox"[^>]*name="is_published"[^>]*checked/', $response->getContent());
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_policy_independently_denies_nonadministrators(): void
    {
        $product = Product::factory()->create();
        foreach ([User::factory()->installer()->create(), User::factory()->administrator()->suspended()->create()] as $user) {
            $this->assertFalse(Gate::forUser($user)->allows('viewAny', Product::class));
            $this->assertFalse(Gate::forUser($user)->allows('create', Product::class));
            $this->assertFalse(Gate::forUser($user)->allows('update', $product));
        }
        $this->assertTrue(Gate::forUser(User::factory()->administrator()->create())->allows('update', $product));
    }

    public function test_creation_omitted_flags_keep_safe_defaults(): void
    {
        $category = Category::factory()->create();
        $data = $this->productData($category);
        unset($data['is_active'], $data['is_published']);
        $this->actingAs(User::factory()->administrator()->create())
            ->post('/admin/products', $data)->assertRedirect('/admin/products');
        $product = Product::sole();
        $this->assertTrue($product->is_active);
        $this->assertFalse($product->is_published);
    }

    public function test_catalog_writes_require_real_csrf_validation(): void
    {
        $category = Category::factory()->create();
        $this->actingAs(User::factory()->administrator()->create());
        $this->app['env'] = 'local';
        try {
            $data = $this->productData($category);
            $this->post('/admin/products', $data)->assertStatus(419);
            $this->post('/admin/categories', ['name' => 'Bloqueada', 'slug' => 'bloqueada'])->assertStatus(419);
            $this->assertDatabaseCount('products', 0);
            $this->assertDatabaseCount('categories', 1);
            $this->withSession(['_token' => 'catalog-test-token'])
                ->post('/admin/products', $data + ['_token' => 'catalog-test-token'])->assertRedirect('/admin/products');
            $product = Product::sole();
            $this->put('/admin/products/'.$product->id, array_replace($data, ['name' => 'Bloqueado']))->assertStatus(419);
            $this->assertSame('Equipo de prueba', $product->fresh()->name);
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_foreign_key_prevents_deleting_referenced_category(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        try {
            $category->delete();
            $this->fail('Una categoría referenciada no debe poder eliminarse.');
        } catch (QueryException) {
            $this->assertDatabaseHas('categories', ['id' => $category->id]);
            $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id]);
        }
    }

    public function test_category_errors_and_old_input_are_scoped_to_failed_form(): void
    {
        $category = Category::factory()->create();
        $other = Category::factory()->create();
        $this->actingAs(User::factory()->administrator()->create())
            ->from('/admin/categories')
            ->put('/admin/categories/'.$category->id, ['name' => 'Nombre rechazado', 'slug' => $other->slug, '_form' => (string) $category->id])
            ->assertSessionHasErrors('slug');
        $response = $this->withCookie(config('session.cookie'), session()->getId())
            ->get('/admin/categories')->assertOk();
        $response->assertSee('id="category-'.$category->id.'-slug-error"', false);
        $response->assertDontSee('id="category-'.$other->id.'-slug-error"', false);
        $response->assertDontSee('id="create-slug-error"', false);
        $response->assertSee('value="Nombre rechazado"', false);
        $response->assertSee('value="'.$other->name.'"', false);
        $this->assertNotSame('Nombre rechazado', $category->fresh()->name);
    }
}
