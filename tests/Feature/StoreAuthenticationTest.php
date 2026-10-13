<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StoreAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (empty(config('app.key'))) {
            config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        }

        Route::middleware(['web', 'auth', 'active.account', 'administrator'])
            ->get('/_test/admin', fn (): string => 'admin');

        Route::middleware(['web', 'auth', 'active.account'])
            ->post('/_test/profile', function (Request $request): JsonResponse {
                $request->user()->fill($request->all())->save();

                return response()->json($request->user()->fresh());
            });

        Route::getRoutes()->refreshNameLookups();
    }

    public function test_guest_cannot_open_admin(): void
    {
        $response = $this->get('/_test/admin');
        $response->assertRedirect(route('login'));

        $jsonResponse = $this->getJson('/_test/admin');
        $jsonResponse->assertStatus(401);
    }

    public function test_installer_cannot_open_admin(): void
    {
        $installer = User::factory()->installer()->create();

        $response = $this->actingAs($installer)->get('/_test/admin');

        $response->assertStatus(403);
    }

    public function test_active_administrator_can_open_admin(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->get('/_test/admin');

        $response->assertOk();
        $response->assertSee('admin');
    }

    public function test_suspended_account_is_denied(): void
    {
        $user = User::factory()->administrator()->suspended()->create();

        $response = $this->actingAs($user)
            ->withSession(['protected_marker' => 'private-session-value'])
            ->get('/_test/admin');

        $response->assertStatus(403);
        $response->assertSessionMissing('protected_marker');
        $this->assertGuest();
    }

    public function test_inactive_account_is_denied(): void
    {
        $user = User::factory()->administrator()->inactive()->create();

        $response = $this->actingAs($user)->get('/_test/admin');

        $response->assertStatus(403);
        $this->assertGuest();
    }

    public function test_unactivated_account_is_denied(): void
    {
        $user = User::factory()->administrator()->create([
            'is_active' => true,
            'activated_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/_test/admin');

        $response->assertStatus(403);
        $this->assertGuest();
    }

    public function test_role_cannot_be_changed_by_request(): void
    {
        $user = User::factory()->installer()->create([
            'name' => 'Original Name',
        ]);

        $originalRole = $user->role;
        $originalActivatedAt = $user->activated_at->toDateTimeString();
        $originalSuspendedAt = $user->suspended_at;

        $response = $this->actingAs($user)->post('/_test/profile', [
            'name' => 'Updated Name',
            'role' => UserRole::Administrator->value,
            'is_active' => false,
            'activated_at' => now()->subYear()->toDateTimeString(),
            'suspended_at' => now()->toDateTimeString(),
        ]);

        $response->assertOk();

        $user->refresh();

        $this->assertSame('Updated Name', $user->name);
        $this->assertSame($originalRole, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame($originalActivatedAt, $user->activated_at->toDateTimeString());
        $this->assertSame($originalSuspendedAt, $user->suspended_at);
    }

    public function test_stale_session_admin_degraded_to_installer_is_denied(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin);

        User::where('id', $admin->id)->update(['role' => UserRole::Installer->value]);

        $response = $this->get('/_test/admin');

        $response->assertStatus(403);
    }

    public function test_stale_session_admin_deactivated_in_db_is_denied(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin);

        User::where('id', $admin->id)->update(['is_active' => false]);

        $response = $this->get('/_test/admin');

        $response->assertStatus(403);
        $this->assertGuest();
    }

    public function test_stale_session_admin_suspended_in_db_is_denied(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin);

        User::where('id', $admin->id)->update(['suspended_at' => now()]);

        $response = $this->get('/_test/admin');

        $response->assertStatus(403);
        $this->assertGuest();
    }

    public function test_stale_session_admin_deleted_in_db_is_denied(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin);

        User::where('id', $admin->id)->delete();

        $response = $this->get('/_test/admin');

        $response->assertStatus(403);
        $this->assertGuest();
    }

    public function test_default_factory_and_database_defaults_have_no_privileges(): void
    {
        $factoryUser = User::factory()->create();

        $this->assertSame(UserRole::Installer, $factoryUser->role);
        $this->assertFalse($factoryUser->is_active);
        $this->assertNull($factoryUser->activated_at);
        $this->assertNull($factoryUser->suspended_at);
        $this->assertFalse($factoryUser->isActive());
        $this->assertFalse($factoryUser->isAdministrator());

        $dbId = DB::table('users')->insertGetId([
            'name' => 'Raw DB User',
            'email' => 'raw_db@example.com',
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rawUser = User::find($dbId);

        $this->assertSame(UserRole::Installer, $rawUser->role);
        $this->assertFalse($rawUser->is_active);
        $this->assertNull($rawUser->activated_at);
        $this->assertNull($rawUser->suspended_at);
        $this->assertFalse($rawUser->isActive());
        $this->assertFalse($rawUser->isAdministrator());
    }

    public function test_enums_and_date_casts(): void
    {
        $user = User::factory()->active()->suspended()->create();

        $this->assertInstanceOf(UserRole::class, $user->role);
        $this->assertInstanceOf(Carbon::class, $user->activated_at);
        $this->assertInstanceOf(Carbon::class, $user->suspended_at);
        $this->assertIsBool($user->is_active);
    }
}
