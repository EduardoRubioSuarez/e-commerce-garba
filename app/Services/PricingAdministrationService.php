<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PriceTier;
use App\Models\AuditEntry;
use App\Models\PriceOverride;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OverflowException;
use RuntimeException;

class PricingAdministrationService
{
    /**
     * Create a new pricing administration service instance.
     */
    public function __construct(
        protected PricingService $pricingService,
    ) {}

    /**
     * Update global store pricing settings.
     *
     * @param  array{public_markup_basis_points: int, installer_markup_basis_points: int, tax_basis_points: int}  $changes
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws ValidationException
     */
    public function updateSettings(array $changes, User $actor): void
    {
        if (! $actor->isActive() || ! $actor->isAdministrator()) {
            throw new AuthorizationException('El usuario no está autorizado.');
        }

        $expectedKeys = [
            'public_markup_basis_points',
            'installer_markup_basis_points',
            'tax_basis_points',
        ];
        if (array_diff(array_keys($changes), $expectedKeys) !== []) {
            throw new InvalidArgumentException('Only pricing settings may be updated.');
        }

        foreach ($expectedKeys as $key) {
            if (! array_key_exists($key, $changes)) {
                throw new InvalidArgumentException("La clave [{$key}] es obligatoria.");
            }

            $value = $changes[$key];
            if (! is_int($value) || $value < 0 || $value > 4294967295) {
                throw new InvalidArgumentException("El valor para [{$key}] debe ser un entero entre 0 y 4294967295.");
            }
        }

        DB::transaction(function () use ($changes, $actor): void {
            StoreSetting::current();
            StoreSetting::query()->where('id', 1)->lockForUpdate()->firstOrFail();

            $settings = StoreSetting::updateCurrent($changes, $actor);

            try {
                Product::query()
                    ->with('priceOverrides')
                    ->where('is_published', true)
                    ->chunkById(100, function (Collection $products) use ($settings): void {
                        foreach ($products as $product) {
                            foreach (PriceTier::cases() as $tier) {
                                $this->pricingService->quoteLine($product, 1, $tier, $settings);
                            }
                        }
                    });
            } catch (InvalidArgumentException|OverflowException $e) {
                throw ValidationException::withMessages([
                    'public_markup' => 'El cambio deja precios publicados inválidos.',
                ]);
            }
        }, 3);
    }

    /**
     * Update price overrides for a specific product.
     *
     * @param  array<string, int|null>  $changes
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws ValidationException
     * @throws RuntimeException
     */
    public function updateOverrides(Product $product, array $changes, User $actor): void
    {
        if (! $actor->isActive() || ! $actor->isAdministrator()) {
            throw new AuthorizationException('El usuario no está autorizado.');
        }

        Gate::forUser($actor)->authorize('update', $product);

        if ($changes === []) {
            throw new InvalidArgumentException('No se proporcionaron cambios de precios.');
        }

        foreach ($changes as $tierKey => $price) {
            if ($tierKey !== 'public' && $tierKey !== 'installer') {
                throw new InvalidArgumentException("Tier inválido [{$tierKey}].");
            }

            if ($price !== null && (! is_int($price) || $price <= 0 || $price > 999999999999)) {
                throw new InvalidArgumentException("Precio inválido para el tier [{$tierKey}].");
            }
        }

        DB::transaction(function () use ($product, $changes, $actor): void {
            StoreSetting::current();
            $settings = StoreSetting::query()->where('id', 1)->lockForUpdate()->firstOrFail();

            $lockedProduct = Product::query()->where('id', $product->id)->lockForUpdate()->firstOrFail();

            $existingOverrides = PriceOverride::query()
                ->where('product_id', $lockedProduct->id)
                ->get()
                ->keyBy(function (PriceOverride $override): string {
                    return $override->tier instanceof PriceTier ? $override->tier->value : (string) $override->tier;
                });

            $auditChanges = [];
            $tierEnumMap = [
                'public' => PriceTier::Public,
                'installer' => PriceTier::Installer,
            ];

            foreach ($changes as $tierKey => $newPrice) {
                $existingOverride = $existingOverrides->get($tierKey);
                $oldPrice = $existingOverride?->base_price_cents;

                if ($oldPrice === $newPrice) {
                    continue;
                }

                $auditChanges[$tierKey] = [
                    'old' => $oldPrice,
                    'new' => $newPrice,
                ];
            }

            if ($auditChanges === []) {
                return;
            }

            foreach ($auditChanges as $tierKey => $diff) {
                $newPrice = $diff['new'];
                $existingOverride = $existingOverrides->get($tierKey);

                if ($newPrice === null) {
                    if ($existingOverride !== null) {
                        $deleted = $existingOverride->delete();
                        if ($deleted === false) {
                            throw new RuntimeException("No se pudo eliminar la excepción para el tier [{$tierKey}].");
                        }
                    }
                } else {
                    if ($existingOverride !== null) {
                        $existingOverride->base_price_cents = $newPrice;
                        $saved = $existingOverride->save();
                        if (! $saved || ! $existingOverride->exists) {
                            throw new RuntimeException("No se pudo actualizar la excepción para el tier [{$tierKey}].");
                        }
                    } else {
                        $override = new PriceOverride([
                            'product_id' => $lockedProduct->id,
                            'tier' => $tierEnumMap[$tierKey],
                            'base_price_cents' => $newPrice,
                        ]);
                        $saved = $override->save();
                        if (! $saved || ! $override->exists) {
                            throw new RuntimeException("No se pudo crear la excepción para el tier [{$tierKey}].");
                        }
                    }
                }
            }

            if ($lockedProduct->is_published) {
                try {
                    $lockedProduct->load('priceOverrides');
                    foreach (PriceTier::cases() as $tier) {
                        $this->pricingService->quoteLine($lockedProduct, 1, $tier, $settings);
                    }
                } catch (InvalidArgumentException|OverflowException $e) {
                    throw ValidationException::withMessages([
                        'public_price' => 'El precio genera una cotización inválida.',
                    ]);
                }
            }

            if ($settings->version >= PHP_INT_MAX) {
                throw new OverflowException('Store setting version overflow.');
            }

            $settings->version = (int) $settings->version + 1;
            $savedSettings = $settings->save();
            if (! $savedSettings) {
                throw new RuntimeException('No se pudo incrementar la versión de configuración.');
            }

            $audit = AuditEntry::create([
                'actor_id' => $actor->id,
                'action' => 'product_pricing.updated',
                'entity_type' => 'product',
                'entity_id' => $lockedProduct->id,
                'changes' => $auditChanges,
            ]);
            if (! $audit->exists) {
                throw new RuntimeException('Audit unavailable');
            }
        }, 3);
    }
}
