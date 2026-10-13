<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StoreLoginTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        Mail::fake();
    }

    public function test_login_and_actual_admin_permissions(): void
    {
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión')->assertSee('name="_token"', false);
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->installer()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->administrator()->create())->get('/admin')->assertOk()->assertSee('Panel de administración')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_login_regenerates_session_and_ignores_redirect_and_role(): void
    {
        $user = User::factory()->administrator()->create(['email' => 'admin@example.test']);
        $this->withSession(['marker' => 'preserved']);
        $id = session()->getId();
        $this->post('/login', ['email' => ' ADMIN@example.test ', 'password' => 'password', 'role' => 'installer', 'redirect' => 'https://example.org'])->assertRedirect(route('admin.second-factor'))->assertSessionHas('marker', 'preserved');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($id, session()->getId());
        $this->assertTrue($user->fresh()->isAdministrator());
    }

    public function test_installer_login_does_not_grant_admin(): void
    {
        $user = User::factory()->installer()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'role' => 'administrator'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->get('/admin')->assertForbidden();
        $this->assertFalse($user->fresh()->isAdministrator());
    }

    public function test_inactive_unactivated_suspended_and_wrong_password_have_generic_error(): void
    {
        $users = [User::factory()->inactive()->create(), User::factory()->active()->create(['activated_at' => null]), User::factory()->administrator()->suspended()->create(), User::factory()->administrator()->create()];
        foreach ($users as $i => $user) {
            $this->post('/login', ['email' => $user->email, 'password' => $i === 3 ? 'wrong' : 'password'])->assertSessionHasErrors(['email' => 'No pudimos iniciar sesión con esos datos.'])->assertSessionMissing('_old_input.password');
            $this->assertGuest();
        }
    }

    public function test_login_throttle_expires(): void
    {
        $user = User::factory()->administrator()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('admin.second-factor'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_invalidates_session_and_csrf_token(): void
    {
        $this->actingAs(User::factory()->administrator()->create())->withSession(['private' => 'secret']);
        $id = session()->getId();
        $token = session()->token();
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('private');
        $this->assertGuest();
        $this->assertNotSame($id, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_real_csrf_rejects_missing_tokens(): void
    {
        $this->app['env'] = 'local';
        try {
            $this->post('/login', ['email' => 'admin@example.test', 'password' => 'password'])->assertStatus(419);
            $this->actingAs(User::factory()->administrator()->create());
            $this->post('/logout')->assertStatus(419);
            $this->assertAuthenticated();
            $this->withSession(['_token' => 'valid-token'])->post('/logout', ['_token' => 'valid-token'])->assertRedirect('/login');
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_registration_and_get_logout_are_unavailable(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/logout')->assertStatus(405);
    }
}
