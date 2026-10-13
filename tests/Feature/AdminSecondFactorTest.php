<?php

namespace Tests\Feature;

use App\Mail\AdministratorLoginCode;
use App\Models\AdminLoginChallenge;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\AdminSecondFactorService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminSecondFactorTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        StoreSetting::factory()->create();
        Mail::fake();
    }

    private function login(): array
    {
        $admin = User::factory()->administrator()->create();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.second-factor'));
        $this->withCookie(config('session.cookie'), session()->getId());
        $code = null;
        Mail::assertSent(AdministratorLoginCode::class, function ($mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });
        $this->assertTrue(is_string($code));

        return [$admin, $code];
    }

    public function test_password_alone_cannot_open_admin_and_verified_code_regenerates_session(): void
    {
        [$admin, $code] = $this->login();
        $id = session()->getId();
        $this->get('/admin')->assertRedirect(route('admin.second-factor'));
        $this->post('/admin/coupons', ['code' => 'BYPASS'])->assertRedirect(route('admin.second-factor'));
        $this->getJson('/admin')->assertForbidden();
        $challenge = AdminLoginChallenge::query()->sole();
        $this->assertTrue(Hash::check($code, $challenge->code_hash));
        $this->assertStringNotContainsString('code_hash', json_encode($challenge));
        $this->post(route('admin.second-factor.verify'), ['code' => $code])->assertRedirect(route('admin.dashboard'));
        $this->assertNotSame($id, session()->getId());
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get('/admin')->assertOk();
        $this->assertNotNull($challenge->fresh()->used_at);
        $this->assertDatabaseCount('notification_attempts', 1);
    }

    public function test_code_is_one_use_and_cannot_cross_sessions(): void
    {
        [$admin, $code] = $this->login();
        $challenge = AdminLoginChallenge::query()->sole();
        try {
            app(AdminSecondFactorService::class)->verify($admin, $challenge->id, 'foreign-session', $code);
            $this->fail('Challenge crossed session boundary.');
        } catch (ValidationException) {
            $this->assertNull($challenge->fresh()->used_at);
        }
        $sessionId = session()->getId();
        $this->post(route('admin.second-factor.verify'), ['code' => $code])->assertRedirect(route('admin.dashboard'));
        $this->expectException(ValidationException::class);
        app(AdminSecondFactorService::class)->verify($admin, $challenge->id, $sessionId, $code);
    }

    public function test_expiration_and_five_failed_attempts_deny_correct_code(): void
    {
        [$admin, $code] = $this->login();
        $wrong = $code === '000000' ? '999999' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.second-factor.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
        }
        $this->assertSame(5, AdminLoginChallenge::query()->sole()->attempts);
        $this->post(route('admin.second-factor.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertNull(session('admin.verified'));
        $this->travel(61)->seconds();
        Mail::fake();
        $this->post(route('admin.second-factor.resend'))->assertRedirect(route('admin.second-factor'));
        $this->travel(301)->seconds();
        $latest = null;
        Mail::assertSent(AdministratorLoginCode::class, function ($mail) use (&$latest): bool {
            $latest = $mail->code;

            return true;
        });
        $this->post(route('admin.second-factor.verify'), ['code' => $latest])->assertSessionHasErrors('code');
    }

    public function test_resend_invalidates_previous_code_and_throttles_abuse(): void
    {
        [$admin, $old] = $this->login();
        $this->post(route('admin.second-factor.resend'))->assertStatus(429);
        $this->travel(61)->seconds();
        Mail::fake();
        $this->post(route('admin.second-factor.resend'))->assertRedirect(route('admin.second-factor'));
        $new = null;
        Mail::assertSent(AdministratorLoginCode::class, function ($mail) use (&$new): bool {
            $new = $mail->code;

            return true;
        });
        $this->post(route('admin.second-factor.verify'), ['code' => $old])->assertSessionHasErrors('code');
        $this->post(route('admin.second-factor.verify'), ['code' => $new])->assertRedirect(route('admin.dashboard'));
        $this->assertDatabaseCount('admin_login_challenges', 2);
    }

    public function test_installer_cannot_enter_second_factor_and_stale_password_session_is_denied(): void
    {
        $this->actingAs(User::factory()->installer()->create())->get(route('admin.second-factor'))->assertForbidden();
        $this->post('/logout');
        [$admin, $code] = $this->login();
        $this->post(route('admin.second-factor.verify'), ['code' => $code])->assertRedirect(route('admin.dashboard'));
        $admin->forceFill(['auth_version' => 1])->save();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get('/admin')->assertForbidden();
        $this->assertGuest();
    }

    private function signOut(): void
    {
        auth()->guard('web')->logout();
        $this->flushSession();
    }

    public function test_remembered_device_skips_code_until_expiry_password_change_or_forget(): void
    {
        [$admin, $code] = $this->login();
        $response = $this->post(route('admin.second-factor.verify'), ['code' => $code, 'remember_device' => '1'])->assertRedirect(route('admin.dashboard'));
        $cookie = $response->getCookie(AdminSecondFactorService::TRUSTED_DEVICE_COOKIE, false);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $token = $cookie->getValue();
        $this->assertDatabaseMissing('trusted_admin_devices', ['token_hash' => $token]);
        $this->assertDatabaseCount('trusted_admin_devices', 1);

        $this->signOut();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'], [])->assertRedirect(route('admin.second-factor'));

        $this->signOut();
        $this->withUnencryptedCookie(AdminSecondFactorService::TRUSTED_DEVICE_COOKIE, $token)
            ->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));

        $this->signOut();
        $this->withUnencryptedCookie(AdminSecondFactorService::TRUSTED_DEVICE_COOKIE, str_repeat('x', 64))
            ->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.second-factor'));

        $this->travel(31)->days();
        $this->signOut();
        $this->withUnencryptedCookie(AdminSecondFactorService::TRUSTED_DEVICE_COOKIE, $token)
            ->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.second-factor'));
        $this->travelBack();

        $admin->forceFill(['auth_version' => $admin->auth_version + 1])->save();
        $this->signOut();
        $this->withUnencryptedCookie(AdminSecondFactorService::TRUSTED_DEVICE_COOKIE, $token)
            ->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.second-factor'));
        $admin->forceFill(['auth_version' => $admin->auth_version - 1])->save();

        app(AdminSecondFactorService::class)->forgetDevices($admin);
        $this->assertDatabaseCount('trusted_admin_devices', 0);
        $this->signOut();
        $this->withUnencryptedCookie(AdminSecondFactorService::TRUSTED_DEVICE_COOKIE, $token)
            ->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.second-factor'));
    }

    public function test_device_is_not_remembered_without_checkbox(): void
    {
        [$admin, $code] = $this->login();
        $response = $this->post(route('admin.second-factor.verify'), ['code' => $code])->assertRedirect(route('admin.dashboard'));
        $this->assertNull($response->getCookie(AdminSecondFactorService::TRUSTED_DEVICE_COOKIE, false));
        $this->assertDatabaseCount('trusted_admin_devices', 0);
    }
}
