<?php

namespace Database\Factories;

use App\Models\StoreSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreSetting>
 */
class StoreSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 1,
            'public_markup_basis_points' => 3000,
            'installer_markup_basis_points' => 1000,
            'tax_basis_points' => 1600,
            'currency' => 'MXN',
            'country' => 'MX',
            'timezone' => 'America/Chihuahua',
            'pickup_name' => 'DEMOSTRACIÓN — Local ficticio',
            'pickup_address' => 'DEMOSTRACIÓN — Dirección pendiente',
            'pickup_hours' => 'DEMOSTRACIÓN — Horario pendiente',
            'contact_email' => 'example@example.com',
            'contact_phone' => null,
            'is_demo' => true,
            'version' => 1,
        ];
    }
}
