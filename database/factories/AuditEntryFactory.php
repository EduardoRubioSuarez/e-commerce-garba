<?php

namespace Database\Factories;

use App\Models\AuditEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEntry>
 */
class AuditEntryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<AuditEntry>
     */
    protected $model = AuditEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => null,
            'action' => 'store_settings.updated',
            'entity_type' => 'store_setting',
            'entity_id' => 1,
            'changes' => [
                'public_markup_basis_points' => [
                    'old' => 3000,
                    'new' => 3500,
                ],
            ],
        ];
    }
}
