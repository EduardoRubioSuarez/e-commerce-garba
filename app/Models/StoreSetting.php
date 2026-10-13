<?php

namespace App\Models;

use Database\Factories\StoreSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OverflowException;
use RuntimeException;

#[Fillable([
    'id',
    'public_markup_basis_points',
    'installer_markup_basis_points',
    'tax_basis_points',
    'currency',
    'country',
    'timezone',
    'pickup_name',
    'pickup_address',
    'pickup_hours',
    'contact_email',
    'contact_phone',
    'is_demo',
    'version',
    'terms',
    'privacy',
    'returns',
    'retention_policy',
])]
class StoreSetting extends Model
{
    /** @use HasFactory<StoreSettingFactory> */
    use HasFactory;

    /**
     * Allowed fields for commercial updates.
     *
     * @var list<string>
     */
    private const ALLOWED_FIELDS = [
        'terms', 'privacy', 'returns', 'retention_policy',
        'public_markup_basis_points',
        'installer_markup_basis_points',
        'tax_basis_points',
        'currency',
        'country',
        'timezone',
        'pickup_name',
        'pickup_address',
        'pickup_hours',
        'contact_email',
        'contact_phone',
        'is_demo',
    ];

    /**
     * Fields that audit full old and new values.
     *
     * @var list<string>
     */
    private const VALUE_AUDITED_FIELDS = [
        'public_markup_basis_points',
        'installer_markup_basis_points',
        'tax_basis_points',
        'currency',
        'country',
        'timezone',
        'is_demo',
    ];

    /**
     * Private contact/pickup fields that must be redacted in audit changes.
     *
     * @var list<string>
     */
    private const REDACTED_AUDITED_FIELDS = [
        'terms', 'privacy', 'returns', 'retention_policy',
        'pickup_name',
        'pickup_address',
        'pickup_hours',
        'contact_email',
        'contact_phone',
    ];

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'public_markup_basis_points' => 'integer',
            'installer_markup_basis_points' => 'integer',
            'tax_basis_points' => 'integer',
            'is_demo' => 'boolean',
            'version' => 'integer',
        ];
    }

    /**
     * Get the singleton store settings instance.
     */
    public static function current(): self
    {
        /** @var array<string, mixed> $defaults */
        $defaults = config('store.defaults', []);

        return static::query()->find(1) ?? static::createOrFirst(['id' => 1], $defaults);
    }

    /**
     * Update the singleton store settings within a locked transaction and record an audit entry.
     *
     * @param array{
     *     public_markup_basis_points?: int,
     *     installer_markup_basis_points?: int,
     *     tax_basis_points?: int,
     *     currency?: string,
     *     country?: string,
     *     timezone?: string,
     *     pickup_name?: string|null,
     *     pickup_address?: string|null,
     *     pickup_hours?: string|null,
     *     contact_email?: string|null,
     *     contact_phone?: string|null,
     *     terms?: string|null,
     *     privacy?: string|null,
     *     returns?: string|null,
     *     retention_policy?: string|null,
     *     is_demo?: bool,
     * } $changes
     *
     * @throws InvalidArgumentException
     */
    public static function updateCurrent(array $changes, ?User $actor = null): self
    {
        foreach (array_keys($changes) as $field) {
            if (! in_array($field, self::ALLOWED_FIELDS, true)) {
                throw new InvalidArgumentException("Field '{$field}' is not allowed for store settings update.");
            }
        }

        return DB::transaction(function () use ($changes, $actor): self {
            static::current();

            /** @var self $setting */
            $setting = static::query()->where('id', 1)->lockForUpdate()->firstOrFail();

            $originalValues = [];
            foreach ($changes as $field => $value) {
                $originalValues[$field] = $setting->getAttribute($field);
            }

            $setting->fill($changes);

            $auditChanges = [];
            foreach ($changes as $field => $value) {
                $oldValue = $originalValues[$field];
                $newValue = $setting->getAttribute($field);

                if (in_array($field, ['public_markup_basis_points', 'installer_markup_basis_points', 'tax_basis_points'], true)) {
                    $oldValue = $oldValue !== null ? (int) $oldValue : null;
                    $newValue = $newValue !== null ? (int) $newValue : null;
                } elseif ($field === 'is_demo') {
                    $oldValue = (bool) $oldValue;
                    $newValue = (bool) $newValue;
                } else {
                    $oldValue = $oldValue !== null ? (string) $oldValue : null;
                    $newValue = $newValue !== null ? (string) $newValue : null;
                }

                if ($oldValue !== $newValue) {
                    if (in_array($field, self::VALUE_AUDITED_FIELDS, true)) {
                        $auditChanges[$field] = [
                            'old' => $oldValue,
                            'new' => $newValue,
                        ];
                    } elseif (in_array($field, self::REDACTED_AUDITED_FIELDS, true)) {
                        $auditChanges[$field] = [
                            'changed' => true,
                        ];
                    }
                }
            }

            if (empty($auditChanges)) {
                return $setting;
            }

            if ($setting->version >= PHP_INT_MAX) {
                throw new OverflowException('Store setting version overflow.');
            }
            $setting->version = $setting->version + 1;
            if (! $setting->save()) {
                throw new RuntimeException('Store setting persistence was cancelled.');
            }

            $audit = AuditEntry::create([
                'actor_id' => $actor?->id,
                'action' => 'store_settings.updated',
                'entity_type' => 'store_setting',
                'entity_id' => $setting->id,
                'changes' => $auditChanges,
            ]);
            if (! $audit->exists) {
                throw new RuntimeException('Audit persistence was cancelled.');
            }

            return $setting;
        }, 3);
    }
}
