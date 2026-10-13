<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_secure_administrator_creation(): void
    {
        $this->artisan('store:create-admin', ['--email' => ' ADMIN@example.test ', '--name' => 'Administrador'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'secure-test-password')
            ->expectsQuestion('Confirma la contraseña', 'secure-test-password')
            ->doesntExpectOutput('secure-test-password')->assertSuccessful();
        $user = User::sole();
        $this->assertSame('admin@example.test', $user->email);
        $this->assertTrue($user->isActive());
        $this->assertTrue($user->isAdministrator());
        $this->assertTrue(Hash::check('secure-test-password', $user->password));
        $this->assertNotSame('secure-test-password', $user->password);
    }

    public function test_duplicate_does_not_promote_installer(): void
    {
        $user = User::factory()->installer()->create();
        $this->artisan('store:create-admin', ['--email' => $user->email])->assertFailed();
        $this->assertDatabaseCount('users', 1);
        $this->assertFalse($user->fresh()->isAdministrator());
    }

    public function test_short_and_mismatched_password_are_rejected(): void
    {
        foreach ([['short', 'short'], ['secure-test-password', 'different-password']] as [$password, $confirmation]) {
            $this->artisan('store:create-admin', ['--email' => 'new@example.test'])
                ->expectsQuestion('Contraseña (mínimo 12 caracteres)', $password)
                ->expectsQuestion('Confirma la contraseña', $confirmation)->assertFailed();
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_invalid_email_and_noninteractive_execution_fail_without_writes(): void
    {
        $this->artisan('store:create-admin', ['--email' => 'invalid'])->assertFailed();
        $this->artisan('store:create-admin', ['--email' => 'new@example.test', '--no-interaction' => true])->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }
}
