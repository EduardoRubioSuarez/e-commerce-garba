<?php

namespace Database\Factories;

use App\Models\InstallerInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InstallerInvitation> */
class InstallerInvitationFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory()->inactive(), 'token_hash' => hash('sha256', bin2hex(random_bytes(32))), 'expires_at' => now()->addHours(48)];
    }
}
