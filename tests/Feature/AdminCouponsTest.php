<?php

namespace Tests\Feature;

use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Models\AuditEntry;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Database\Seeders\StoreDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminCouponsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        StoreSetting::factory()->create(['timezone' => 'America/Chihuahua']);
        Route::post('/_tests/coupon-definition', fn (StoreCouponRequest $request) => response()->json($request->attributesForPersistence()))->middleware('web');
        Route::put('/_tests/coupon-definition/{coupon}', fn (UpdateCouponRequest $request, Coupon $coupon) => response()->json($request->attributesForPersistence()))->middleware('web');
    }

    /** @return array<string, mixed> */
    private function payload(array $changes = []): array
    {
        return array_replace(['code' => ' promo10 ', 'kind' => 'percentage', 'value' => '10', 'currency' => 'MXN',
            'starts_at' => '2026-10-07T10:00', 'ends_at' => '2026-10-08T10:00', 'minimum_base' => '0',
            'max_uses' => 50, 'scope' => 'all'], $changes);
    }

    public function test_request_normalizes_exact_values_defaults_and_timezone(): void
    {
        $this->actingAs(User::factory()->administrator()->create())->postJson('/_tests/coupon-definition', $this->payload())
            ->assertOk()->assertJsonPath('code', 'PROMO10')->assertJsonPath('value', 1000)
            ->assertJsonPath('minimum_base_minor', 0)->assertJsonPath('applies_to_installers', false)
            ->assertJsonPath('applies_to_manual_prices', false)->assertJsonPath('starts_at', '2026-10-07T16:00:00.000000Z');
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidDefinitions(): array
    {
        return [
            'over ninety' => [['value' => '90.01'], 'value'], 'zero' => [['value' => '0'], 'value'],
            'negative' => [['value' => '-1'], 'value'], 'exponent' => [['value' => '1e2'], 'value'],
            'fraction precision' => [['value' => '10.001'], 'value'], 'unknown kind' => [['kind' => 'other'], 'kind'],
            'currency mismatch' => [['kind' => 'fixed', 'currency' => 'USD'], 'currency'],
            'inverted dates' => [['ends_at' => '2026-10-06T10:00'], 'ends_at'],
            'equal dates' => [['ends_at' => '2026-10-07T10:00'], 'ends_at'],
            'invalid date' => [['starts_at' => '2026-02-30T10:00'], 'starts_at'],
            'selected empty' => [['scope' => 'selected'], 'product_ids'],
            'unknown product' => [['scope' => 'selected', 'product_ids' => [999]], 'product_ids.0'],
            'unknown scope' => [['scope' => 'unknown'], 'scope'], 'no capacity' => [['max_uses' => 0], 'max_uses'],
            'negative minimum' => [['minimum_base' => '-1'], 'minimum_base'],
            'invalid flag' => [['applies_to_installers' => 'yes'], 'applies_to_installers'],
            'invalid code' => [['code' => 'A B'], 'code'],
        ];
    }

    #[DataProvider('invalidDefinitions')]
    public function test_invalid_definition_is_rejected(array $changes, string $field): void
    {
        $this->actingAs(User::factory()->administrator()->create())->postJson('/_tests/coupon-definition', $this->payload($changes))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_selected_products_fixed_money_and_percentage_boundary(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->administrator()->create())->postJson('/_tests/coupon-definition',
            $this->payload(['scope' => 'selected', 'product_ids' => [$product->id], 'kind' => 'fixed', 'value' => '12.34', 'minimum_base' => '1300.01']))
            ->assertOk()->assertJsonPath('value', 1234)->assertJsonPath('minimum_base_minor', 130001);
        $this->postJson('/_tests/coupon-definition', $this->payload(['value' => '90']))->assertOk()->assertJsonPath('value', 9000);
    }

    public function test_code_uniqueness_includes_disabled_coupons_and_update_ignores_only_itself(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'promo10', 'is_active' => false]);
        $this->assertSame('PROMO10', $coupon->fresh()->code);
        $this->actingAs(User::factory()->administrator()->create())->postJson('/_tests/coupon-definition', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->putJson('/_tests/coupon-definition/'.$coupon->id, $this->payload())->assertOk();
        $other = Coupon::factory()->create(['code' => 'OTHER']);
        $this->putJson('/_tests/coupon-definition/'.$other->id, $this->payload())->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_definition_requests_require_active_administrator(): void
    {
        $this->postJson('/_tests/coupon-definition', $this->payload())->assertForbidden();
        foreach ([User::factory()->installer()->create(), User::factory()->administrator()->suspended()->create()] as $user) {
            $this->actingAs($user)->postJson('/_tests/coupon-definition', $this->payload())->assertForbidden();
        }
    }

    public function test_panel_access_is_restricted_to_active_administrators(): void
    {
        $this->get('/admin/coupons')->assertRedirect('/login');
        foreach ([User::factory()->installer()->create(), User::factory()->administrator()->suspended()->create()] as $user) {
            $this->actingAs($user)->get('/admin/coupons')->assertForbidden();
            $this->actingAs($user)->post('/admin/coupons', $this->payload())->assertForbidden();
        }
    }

    public function test_admin_creates_updates_and_deactivates_without_deleting_history(): void
    {
        $product = Product::factory()->create();
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->post('/admin/coupons', $this->payload(['scope' => 'selected', 'product_ids' => [$product->id]]))
            ->assertRedirect('/admin/coupons');
        $coupon = Coupon::query()->sole();
        $this->assertSame([$product->id], $coupon->products()->pluck('products.id')->all());
        $this->assertDatabaseHas('audit_entries', ['action' => 'coupon.created', 'actor_id' => $admin->id]);
        $this->put('/admin/coupons/'.$coupon->id, $this->payload(['is_active' => '0']))->assertRedirect('/admin/coupons');
        $this->assertFalse($coupon->fresh()->is_active);
        $this->assertSame(0, $coupon->products()->count());
        $this->assertDatabaseCount('audit_entries', 2);
        $this->delete('/admin/coupons/'.$coupon->id)->assertMethodNotAllowed();
        $this->assertDatabaseCount('coupons', 1);
    }

    public function test_panel_displays_local_dates_conditions_and_escaped_product_labels(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'PROMO10', 'starts_at' => '2026-10-07 16:00:00', 'ends_at' => '2026-10-08 16:00:00']);
        Product::factory()->create(['name' => '<script>bad</script>']);
        $this->actingAs(User::factory()->administrator()->create())->get('/admin/coupons')->assertOk()
            ->assertSee('PROMO10')->assertSee('sin IVA')->assertSee('America/Chihuahua')->assertSee('no activa')->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/admin/coupons/'.$coupon->id.'/edit')->assertOk()->assertSee('2026-10-07T10:00')
            ->assertSee('&lt;script&gt;bad&lt;/script&gt;', false)->assertDontSee('<script>bad</script>', false);
    }

    public function test_audit_failure_rolls_back_coupon_and_selected_products(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        AuditEntry::creating(fn () => false);
        $this->post('/admin/coupons', $this->payload())->assertStatus(500);
        $this->assertDatabaseCount('coupons', 0);
        $this->assertDatabaseCount('audit_entries', 0);
    }

    public function test_invalid_panel_input_preserves_values_and_exposes_accessible_error(): void
    {
        $this->actingAs(User::factory()->administrator()->create())->from('/admin/coupons/create')
            ->post('/admin/coupons', $this->payload(['value' => '91']))->assertRedirect('/admin/coupons/create')->assertSessionHasErrors('value');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get('/admin/coupons/create')->assertOk()->assertSee('aria-invalid="true"', false)->assertSee('role="alert"', false)->assertSee('value="91"', false);
    }

    public function test_demo_coupon_is_explicit_idempotent_and_does_not_overwrite_edits(): void
    {
        $this->seed(StoreDemoSeeder::class);
        $coupon = Coupon::query()->sole();
        $this->assertSame('PROMO10', $coupon->code);
        $this->assertSame(1000, $coupon->value);
        $this->assertSame(50, $coupon->max_uses);
        $this->assertFalse($coupon->applies_to_installers);
        $coupon->update(['value' => 500, 'is_active' => false]);
        $this->seed(StoreDemoSeeder::class);
        $this->assertDatabaseCount('coupons', 1);
        $this->assertSame(500, $coupon->fresh()->value);
        $this->assertFalse($coupon->fresh()->is_active);
    }
}
