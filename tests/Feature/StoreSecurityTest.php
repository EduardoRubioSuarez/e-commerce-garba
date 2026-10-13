<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPhoto;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\OrderAccessService;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StoreSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'app.debug' => false]);
    }

    public function test_public_html_json_metadata_and_errors_do_not_expose_cost_or_private_tier(): void
    {
        StoreSetting::current();
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000, 'description' => '<script>alert("xss")</script>']);
        $public = $this->get('/productos/'.$product->slug)->assertOk()->assertSee('$1,508.00')->assertSee($product->description)
            ->assertDontSee($product->description, false)->assertDontSee('$1,276.00')->assertDontSee('base_cost_cents')->assertHeader('Cache-Control', 'no-store, private');
        $json = $this->getJson('/productos/'.$product->slug)->assertOk()->assertJsonPath('data.price.total_cents', 150800);
        foreach ([$public->getContent(), $json->getContent()] as $content) {
            foreach (['base_cost_cents', 'installer_markup_basis_points', '100000', '127600', 'token_hash'] as $private) {
                $this->assertStringNotContainsString($private, $content);
            }
        }
        $this->getJson('/productos/missing')->assertNotFound()->assertDontSee('trace')->assertDontSee('SQLSTATE')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_private_price_is_recomputed_after_suspension_without_reusing_cache(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $installer = User::factory()->installer()->create();
        $this->actingAs($installer)->getJson('/productos/'.$product->slug)->assertJsonPath('data.price.total_cents', 127600)->assertHeader('Cache-Control', 'no-store, private');
        User::query()->whereKey($installer->id)->update(['suspended_at' => now()]);
        $this->getJson('/productos/'.$product->slug)->assertJsonPath('data.price.total_cents', 150800);
        $this->get('/admin/orders')->assertForbidden();
    }

    public function test_order_idor_token_and_serialization_do_not_reveal_foreign_contact(): void
    {
        $owner = User::factory()->installer()->create();
        $order = Order::factory()->create(['user_id' => $owner->id, 'contact_email' => 'foreign-contact@example.test']);
        $token = app(OrderAccessService::class)->issue($order);
        $this->actingAs(User::factory()->installer()->create());
        $this->getJson('/pedidos/'.$order->reference)->assertNotFound()->assertDontSee('foreign-contact@example.test')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->getJson('/pedidos/'.$order->reference.'?token=invalid')->assertNotFound();
        $serialized = $order->fresh()->toJson();
        foreach ([$token, $order->fresh()->token_hash, 'foreign-contact@example.test', $order->phone_canonical] as $private) {
            $this->assertStringNotContainsString($private, $serialized);
        }
    }

    public function test_privileged_mass_assignment_cannot_change_role_or_money(): void
    {
        $user = User::factory()->installer()->create();
        $user->fill(['role' => UserRole::Administrator->value, 'is_active' => false, 'auth_version' => 999])->save();
        $this->assertSame(UserRole::Installer, $user->fresh()->role);
        $this->assertTrue($user->fresh()->is_active);
        $order = Order::factory()->create();
        try {
            $order->fill(['total_cents' => 1, 'status' => 'paid'])->save();
        } catch (MassAssignmentException) {
            $this->assertFalse($order->isDirty());
        }
        $this->assertSame(150800, $order->fresh()->total_cents);
        $this->assertSame('pending', $order->fresh()->status->value);
    }

    #[DataProvider('adminPaths')]
    public function test_administrative_errors_are_private_and_do_not_expose_pii(string $path): void
    {
        $this->actingAs(User::factory()->installer()->create())->getJson($path)->assertForbidden()->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('trace');
    }

    public static function adminPaths(): array
    {
        return [['/admin/orders'], ['/admin/settings'], ['/admin/notifications'], ['/admin/products']];
    }

    public function test_cross_product_photo_idor_is_denied_and_executable_upload_is_rejected(): void
    {
        Storage::fake('public');
        $one = Product::factory()->create();
        $two = Product::factory()->create();
        $photo = ProductPhoto::factory()->create(['product_id' => $two->id]);
        $this->actingAs(User::factory()->administrator()->create())->putJson('/admin/products/'.$one->id.'/photos/'.$photo->id,
            ['alt' => 'Foreign mutation', 'sort_order' => 0])->assertNotFound();
        $this->assertNotSame('Foreign mutation', $photo->fresh()->alt);
        $this->postJson('/admin/products/'.$one->id.'/photos', ['photo' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'), 'alt' => 'Shell'])->assertUnprocessable();
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_local_post_requires_csrf_and_webhook_requires_signature(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        try {
            $this->postJson('/login', ['email' => 'someone@example.test', 'password' => 'password'])->assertStatus(419);
            $this->postJson('/checkout', [])->assertStatus(419);
            $this->postJson('/stripe/webhook', ['id' => 'evt_forged'])->assertStatus(400);
        } finally {
            $this->app->detectEnvironment(fn (): string => 'testing');
        }
    }

    public function test_production_session_cookie_is_secure_http_only_and_same_site(): void
    {
        $environment = Env::getRepository();
        $previous = $environment->get('SESSION_SECURE_COOKIE');
        $previousAppEnv = [$_ENV['APP_ENV'] ?? null, $_SERVER['APP_ENV'] ?? null, getenv('APP_ENV')];
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';
        putenv('APP_ENV=production');
        $environment->clear('SESSION_SECURE_COOKIE');
        $this->app->detectEnvironment(fn (): string => 'production');
        try {
            $profile = require base_path('config/session.php');
            config(['session.secure' => $profile['secure'], 'session.http_only' => $profile['http_only'], 'session.same_site' => $profile['same_site']]);
            $response = $this->get('http://localhost/login')->assertOk();
            $cookies = $response->headers->getCookies();
            $session = collect($cookies)->first(fn ($cookie): bool => $cookie->getName() === config('session.cookie'));
            $this->assertNotNull($session);
            $this->assertTrue($session->isSecure());
            $this->assertTrue($session->isHttpOnly());
            $this->assertSame('lax', $session->getSameSite());
        } finally {
            $this->app->detectEnvironment(fn (): string => 'testing');
            foreach (['_ENV', '_SERVER'] as $index => $global) {
                if ($previousAppEnv[$index] === null) {
                    unset($GLOBALS[$global]['APP_ENV']);
                } else {
                    $GLOBALS[$global]['APP_ENV'] = $previousAppEnv[$index];
                }
            }
            putenv($previousAppEnv[2] === false ? 'APP_ENV' : 'APP_ENV='.$previousAppEnv[2]);
            if ($previous !== null) {
                $environment->set('SESSION_SECURE_COOKIE', $previous);
            }
        }
    }
}
