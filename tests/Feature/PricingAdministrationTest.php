<?php

namespace Tests\Feature;

use App\Data\CartLineData;
use App\Data\QuoteData;
use App\Enums\PriceTier;
use App\Models\AuditEntry;
use App\Models\Category;
use App\Models\PriceOverride;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\PricingAdministrationService;
use App\Services\PricingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PricingAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
    }

    private function quote(Product $product, PriceTier $tier = PriceTier::Public): QuoteData
    {
        return app(PricingService::class)->quote([new CartLineData($product->id, 1)], $tier);
    }

    public function test_guests_cannot_access_pricing(): void
    {
        $product = Product::factory()->create();
        $this->get('/admin/pricing')->assertRedirect('/login');
        $this->put('/admin/pricing', [])->assertRedirect('/login');
        $this->put('/admin/products/'.$product->id.'/pricing', [])->assertRedirect('/login');
        $this->assertDatabaseCount('audit_entries', 0);
    }

    public function test_nonadministrators_and_suspended_accounts_cannot_change_prices(): void
    {
        $product = Product::factory()->create();
        foreach ([User::factory()->installer()->create(), User::factory()->administrator()->suspended()->create()] as $user) {
            $this->actingAs($user)->get('/admin/pricing')->assertForbidden();
            $this->actingAs($user)->put('/admin/pricing', ['public_markup' => '30', 'installer_markup' => '10', 'tax_rate' => '16'])->assertForbidden();
            $this->actingAs($user)->put('/admin/products/'.$product->id.'/pricing', ['public_price' => '1200'])->assertForbidden();
        }
        $this->assertDatabaseCount('audit_entries', 0);
    }

    public function test_admin_panel_previews_both_tiers_and_escapes_names(): void
    {
        StoreSetting::factory()->create();
        Product::factory()->create(['name' => '<script>bad</script>', 'base_cost_cents' => 100000]);
        $this->actingAs(User::factory()->administrator()->create())->get('/admin/pricing')
            ->assertOk()->assertSee('1300.00')->assertSee('1508.00')->assertSee('1100.00')->assertSee('1276.00')
            ->assertSee('sin IVA')->assertSee('Automático')->assertSee('&lt;script&gt;bad&lt;/script&gt;', false)->assertDontSee('<script>bad</script>', false)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_settings_update_uses_exact_basis_points_and_audits_actor(): void
    {
        StoreSetting::factory()->create();
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->put('/admin/pricing', ['public_markup' => '30.25', 'installer_markup' => '10.01', 'tax_rate' => '16.50', 'version' => 999, 'public_markup_basis_points' => 1, 'currency' => 'USD'])
            ->assertRedirect('/admin/pricing');
        $settings = StoreSetting::current();
        $this->assertSame(3025, $settings->public_markup_basis_points);
        $this->assertSame(1001, $settings->installer_markup_basis_points);
        $this->assertSame(1650, $settings->tax_basis_points);
        $this->assertSame('MXN', $settings->currency);
        $this->assertSame(2, $settings->version);
        $audit = AuditEntry::sole();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame('store_settings.updated', $audit->action);
        $this->assertSame(['old' => 3000, 'new' => 3025], $audit->changes['public_markup_basis_points']);
    }

    public function test_settings_noop_does_not_bump_version_or_audit(): void
    {
        StoreSetting::factory()->create();
        $this->actingAs(User::factory()->administrator()->create())->put('/admin/pricing', ['public_markup' => '30.00', 'installer_markup' => '10', 'tax_rate' => '16'])->assertRedirect('/admin/pricing');
        $this->assertSame(1, StoreSetting::current()->version);
        $this->assertDatabaseCount('audit_entries', 0);
    }

    public function test_override_is_independent_and_survives_cost_and_percentage_changes(): void
    {
        StoreSetting::factory()->create();
        $product = Product::factory()->create(['base_cost_cents' => 100000]);
        $admin = User::factory()->administrator()->create();
        $before = $this->quote($product)->fingerprint;
        $this->actingAs($admin)->put('/admin/products/'.$product->id.'/pricing', ['public_price' => '1200.01', 'product_id' => 999, 'base_price_cents' => 1, 'tier' => 'installer'])->assertRedirect('/admin/pricing');
        $override = PriceOverride::sole();
        $this->assertSame($product->id, $override->product_id);
        $this->assertSame(PriceTier::Public, $override->tier);
        $this->assertSame(120001, $override->base_price_cents);
        $quote = $this->quote($product);
        $this->assertSame(120001, $quote->lines[0]->unitBaseCents);
        $this->assertSame(139201, $quote->totalCents);
        $this->assertSame('manual', $quote->lines[0]->priceSource);
        $this->assertNotSame($before, $quote->fingerprint);
        $this->assertSame(110000, $this->quote($product, PriceTier::Installer)->lines[0]->unitBaseCents);
        $product->update(['base_cost_cents' => 200000]);
        $this->actingAs($admin)->put('/admin/pricing', ['public_markup' => '40', 'installer_markup' => '20', 'tax_rate' => '16'])->assertRedirect('/admin/pricing');
        $this->assertSame(120001, $this->quote($product)->lines[0]->unitBaseCents);
        $this->assertSame(240000, $this->quote($product, PriceTier::Installer)->lines[0]->unitBaseCents);
        $this->assertSame(120001, $override->fresh()->base_price_cents);
    }

    public function test_clearing_one_override_restores_automatic_and_preserves_other(): void
    {
        StoreSetting::factory()->create();
        $product = Product::factory()->create(['base_cost_cents' => 100000]);
        $this->actingAs(User::factory()->administrator()->create());
        $url = '/admin/products/'.$product->id.'/pricing';
        $this->put($url, ['public_price' => '1200', 'installer_price' => '1050'])->assertRedirect('/admin/pricing');
        $this->put($url, ['public_price' => null])->assertRedirect('/admin/pricing');
        $this->assertDatabaseCount('price_overrides', 1);
        $this->assertSame(130000, $this->quote($product)->lines[0]->unitBaseCents);
        $this->assertSame('automatic', $this->quote($product)->lines[0]->priceSource);
        $this->assertSame(105000, $this->quote($product, PriceTier::Installer)->lines[0]->unitBaseCents);
        $this->assertSame(3, StoreSetting::current()->version);
    }

    public function test_override_audits_before_after_and_noop_is_silent(): void
    {
        StoreSetting::factory()->create();
        $product = Product::factory()->create();
        $admin = User::factory()->administrator()->create();
        $url = '/admin/products/'.$product->id.'/pricing';
        $this->actingAs($admin)->put($url, ['public_price' => '1.01'])->assertRedirect('/admin/pricing');
        $audit = AuditEntry::sole();
        $this->assertSame('product_pricing.updated', $audit->action);
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame($product->id, $audit->entity_id);
        $this->assertSame(['old' => null, 'new' => 101], $audit->changes['public']);
        $this->put($url, ['public_price' => '1.01'])->assertRedirect('/admin/pricing');
        $this->assertDatabaseCount('audit_entries', 1);
        $this->assertSame(2, StoreSetting::current()->version);
        $this->put($url, ['public_price' => ''])->assertRedirect('/admin/pricing');
        $this->assertSame(['old' => 101, 'new' => null], AuditEntry::latest('id')->first()->changes['public']);
    }

    #[DataProvider('invalidPrices')]
    public function test_invalid_manual_prices_do_not_write(mixed $value): void
    {
        StoreSetting::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->administrator()->create())->put('/admin/products/'.$product->id.'/pricing', ['public_price' => $value])->assertSessionHasErrors('public_price');
        $this->assertDatabaseCount('price_overrides', 0);
        $this->assertDatabaseCount('audit_entries', 0);
        $this->assertSame(1, StoreSetting::current()->version);
    }

    public static function invalidPrices(): array
    {
        return [['0'], ['-1'], ['1.001'], ['1e3'], ['1,00'], ['10000000000.00'], [['1']], [1.5]];
    }

    #[DataProvider('invalidRates')]
    public function test_invalid_rates_do_not_write(mixed $value): void
    {
        StoreSetting::factory()->create();
        $this->actingAs(User::factory()->administrator()->create())->put('/admin/pricing', ['public_markup' => $value, 'installer_markup' => '10', 'tax_rate' => '16'])->assertSessionHasErrors('public_markup');
        $this->assertSame(3000, StoreSetting::current()->public_markup_basis_points);
        $this->assertDatabaseCount('audit_entries', 0);
    }

    public static function invalidRates(): array
    {
        return [['-1'], ['30.001'], ['1e2'], ['42949672.96'], [['1']], [30.5]];
    }

    public function test_schema_prevents_duplicate_tiers_for_a_product(): void
    {
        $product = Product::factory()->create();
        PriceOverride::factory()->create(['product_id' => $product->id, 'tier' => PriceTier::Public]);
        $this->expectException(QueryException::class);
        PriceOverride::factory()->create(['product_id' => $product->id, 'tier' => PriceTier::Public]);
    }

    public function test_loaded_product_serialization_does_not_expose_overrides(): void
    {
        $product = Product::factory()->create();
        PriceOverride::factory()->create(['product_id' => $product->id]);
        $serialized = $product->load('priceOverrides')->toArray();
        $this->assertArrayNotHasKey('price_overrides', $serialized);
        $this->assertArrayNotHasKey('base_cost_cents', $serialized);
    }

    public function test_override_foreign_key_preserves_referenced_product(): void
    {
        $product = Product::factory()->create();
        PriceOverride::factory()->create(['product_id' => $product->id]);
        $this->expectException(QueryException::class);
        $product->delete();
    }

    public function test_quotes_reject_corrupted_nonpositive_override(): void
    {
        StoreSetting::factory()->create();
        $product = Product::factory()->create();
        PriceOverride::factory()->create(['product_id' => $product->id, 'tier' => PriceTier::Public, 'base_price_cents' => 0]);
        $this->expectException(\InvalidArgumentException::class);
        $this->quote($product);
    }

    public function test_cannot_publish_product_with_invalid_effective_price(): void
    {
        StoreSetting::factory()->create();
        $product = Product::factory()->create();
        PriceOverride::factory()->create(['product_id' => $product->id, 'tier' => PriceTier::Installer, 'base_price_cents' => 0]);
        $this->actingAs(User::factory()->administrator()->create())->put('/admin/products/'.$product->id, [
            'category_id' => $product->category_id, 'name' => 'Intento inválido', 'slug' => $product->slug,
            'base_cost' => '1000', 'is_active' => true, 'is_published' => true,
        ])->assertSessionHasErrors('is_published');
        $this->assertFalse($product->fresh()->is_published);
        $this->assertNotSame('Intento inválido', $product->fresh()->name);
    }

    public function test_publication_checks_new_products_and_price_overflow(): void
    {
        StoreSetting::factory()->create(['tax_basis_points' => PHP_INT_MAX]);
        $category = Category::factory()->create();
        $this->actingAs(User::factory()->administrator()->create())->post('/admin/products', [
            'category_id' => $category->id, 'name' => 'No publicar', 'slug' => 'no-publicar',
            'base_cost' => '9999999999.99', 'is_active' => true, 'is_published' => true,
        ])->assertSessionHasErrors('is_published');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_bad_global_change_rolls_back_settings_version_and_audit(): void
    {
        StoreSetting::factory()->create();
        Product::factory()->published()->create(['base_cost_cents' => PHP_INT_MAX]);
        $this->actingAs(User::factory()->administrator()->create())->put('/admin/pricing', ['public_markup' => '40', 'installer_markup' => '10', 'tax_rate' => '16'])->assertSessionHasErrors('public_markup');
        $this->assertSame(3000, StoreSetting::current()->public_markup_basis_points);
        $this->assertSame(1, StoreSetting::current()->version);
        $this->assertDatabaseCount('audit_entries', 0);
    }

    public function test_audit_failure_rolls_back_override_and_version(): void
    {
        StoreSetting::factory()->create();
        $product = Product::factory()->create();
        $admin = User::factory()->administrator()->create();
        AuditEntry::creating(fn () => throw new RuntimeException('Audit unavailable'));
        $this->withoutExceptionHandling()->actingAs($admin);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit unavailable');
        try {
            $this->put('/admin/products/'.$product->id.'/pricing', ['public_price' => '1200']);
        } finally {
            $this->assertDatabaseCount('price_overrides', 0);
            $this->assertSame(1, StoreSetting::current()->version);
            $this->assertDatabaseCount('audit_entries', 0);
            AuditEntry::flushEventListeners();
        }
    }

    public function test_cancelled_audit_does_not_commit_global_prices(): void
    {
        StoreSetting::factory()->create();
        $this->actingAs(User::factory()->administrator()->create());
        AuditEntry::creating(fn (): bool => false);
        try {
            $this->put('/admin/pricing', ['public_markup' => '40', 'installer_markup' => '10', 'tax_rate' => '16'])->assertStatus(500);
            $this->assertSame(3000, StoreSetting::current()->public_markup_basis_points);
            $this->assertSame(1, StoreSetting::current()->version);
            $this->assertDatabaseCount('audit_entries', 0);
        } finally {
            AuditEntry::flushEventListeners();
        }
    }

    public function test_pricing_mutations_require_real_csrf(): void
    {
        StoreSetting::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->administrator()->create());
        $this->app['env'] = 'local';
        try {
            $this->put('/admin/pricing', ['public_markup' => '40', 'installer_markup' => '10', 'tax_rate' => '16'])->assertStatus(419);
            $this->put('/admin/products/'.$product->id.'/pricing', ['public_price' => '1200'])->assertStatus(419);
            $this->assertSame(1, StoreSetting::current()->version);
            $this->assertDatabaseCount('audit_entries', 0);
            $this->withSession(['_token' => 'pricing-csrf-token'])->put('/admin/products/'.$product->id.'/pricing', ['public_price' => '1200', '_token' => 'pricing-csrf-token'])->assertRedirect('/admin/pricing');
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_saved_product_stays_on_its_pricing_page(): void
    {
        StoreSetting::factory()->create();
        $products = Product::factory()->count(21)->create();
        $product = $products->last();
        $this->actingAs(User::factory()->administrator()->create())->get('/admin/pricing?page=2')->assertOk()->assertSee($product->name);
        $this->put('/admin/products/'.$product->id.'/pricing', ['public_price' => '1200', 'page' => '2'])->assertRedirect('/admin/pricing?page=2');
    }

    public function test_global_version_cannot_overflow(): void
    {
        StoreSetting::factory()->create(['version' => PHP_INT_MAX]);
        $this->withoutExceptionHandling()->actingAs(User::factory()->administrator()->create());
        $this->expectException(\OverflowException::class);
        try {
            $this->put('/admin/pricing', ['public_markup' => '40', 'installer_markup' => '10', 'tax_rate' => '16']);
        } finally {
            $this->assertSame(PHP_INT_MAX, StoreSetting::current()->version);
            $this->assertSame(3000, StoreSetting::current()->public_markup_basis_points);
            $this->assertDatabaseCount('audit_entries', 0);
        }
    }

    public function test_administration_service_rejects_unrelated_setting_keys(): void
    {
        StoreSetting::factory()->create();
        $admin = User::factory()->administrator()->create();
        $this->expectException(\InvalidArgumentException::class);
        app(PricingAdministrationService::class)->updateSettings([
            'public_markup_basis_points' => 3000, 'installer_markup_basis_points' => 1000, 'tax_basis_points' => 1600, 'currency' => 'USD',
        ], $admin);
    }

    public function test_cancelled_override_creation_rolls_back_without_success(): void
    {
        StoreSetting::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->administrator()->create());
        PriceOverride::creating(fn (): bool => false);
        try {
            $this->put('/admin/products/'.$product->id.'/pricing', ['public_price' => '1200'])->assertStatus(500);
            $this->assertDatabaseCount('price_overrides', 0);
            $this->assertSame(1, StoreSetting::current()->version);
            $this->assertDatabaseCount('audit_entries', 0);
        } finally {
            PriceOverride::flushEventListeners();
        }
    }

    #[DataProvider('cancelledMutations')]
    public function test_cancelled_pricing_events_roll_back_all_changes(string $mutation): void
    {
        StoreSetting::factory()->create();
        $product = Product::factory()->create();
        PriceOverride::factory()->create(['product_id' => $product->id, 'base_price_cents' => 120000]);
        $this->actingAs(User::factory()->administrator()->create());
        $value = '1000';
        switch ($mutation) {
            case 'update':
                PriceOverride::updating(fn (): bool => false);
                break;
            case 'delete':
                PriceOverride::deleting(fn (): bool => false);
                $value = null;
                break;
            case 'version':
                StoreSetting::updating(fn (): bool => false);
                break;
            case 'audit':
                AuditEntry::creating(fn (): bool => false);
                break;
        }
        try {
            $this->put('/admin/products/'.$product->id.'/pricing', ['public_price' => $value])->assertStatus(500);
            $this->assertSame(120000, PriceOverride::sole()->base_price_cents);
            $this->assertSame(1, StoreSetting::current()->version);
            $this->assertDatabaseCount('audit_entries', 0);
        } finally {
            PriceOverride::flushEventListeners();
            StoreSetting::flushEventListeners();
            AuditEntry::flushEventListeners();
        }
    }

    public static function cancelledMutations(): array
    {
        return [['update'], ['delete'], ['version'], ['audit']];
    }

    public function test_override_version_overflow_rolls_back_price(): void
    {
        StoreSetting::factory()->create(['version' => PHP_INT_MAX]);
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->administrator()->create())->put('/admin/products/'.$product->id.'/pricing', ['public_price' => '1200'])->assertStatus(500);
        $this->assertSame(PHP_INT_MAX, StoreSetting::current()->version);
        $this->assertDatabaseCount('price_overrides', 0);
        $this->assertDatabaseCount('audit_entries', 0);
    }

    public function test_internal_administration_rejects_nonadministrator(): void
    {
        $product = Product::factory()->create();
        $actor = User::factory()->installer()->create();
        $this->expectException(AuthorizationException::class);
        app(PricingAdministrationService::class)->updateOverrides($product, ['public' => 120000], $actor);
    }
}
