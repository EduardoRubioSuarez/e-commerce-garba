<?php

namespace Tests;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Establish an authenticated test session, including completed admin MFA. */
    public function actingAs(Authenticatable $user, $guard = null): static
    {
        if ($user instanceof User) {
            $this->withSession(['auth.version' => $user->auth_version]);
            if ($user->isAdministrator()) {
                $this->withSession(['admin.verified' => ['user_id' => $user->id, 'auth_version' => $user->auth_version]]);
            }
        }

        return parent::actingAs($user, $guard);
    }
}
