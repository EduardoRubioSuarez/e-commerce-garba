<?php

namespace Tests\Feature;

use App\Enums\PriceTier;
use App\Enums\UserRole;
use App\Models\AuditEntry;
use App\Models\StoreSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class StoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test store settings initializes with exact fictitious defaults and integer/bool types.
     */
    public function test_defaults_are_explicit_demo_values(): void
    {
        $setting = StoreSetting::current();

        $this->assertSame(1, $setting->id);
        $this->assertIsInt($setting->id);

        $this->assertSame(3000, $setting->public_markup_basis_points);
        $this->assertIsInt($setting->public_markup_basis_points);

        $this->assertSame(1000, $setting->installer_markup_basis_points);
        $this->assertIsInt($setting->installer_markup_basis_points);

        $this->assertSame(1600, $setting->tax_basis_points);
        $this->assertIsInt($setting->tax_basis_points);

        $this->assertSame('MXN', $setting->currency);
        $this->assertSame('MX', $setting->country);
        $this->assertSame('America/Chihuahua', $setting->timezone);
        $this->assertSame('DEMOSTRACIÓN — Local ficticio', $setting->pickup_name);
        $this->assertSame('C. Toronja 561 col. Indeco 23070 La Paz, B.C.S.', $setting->pickup_address);
        $this->assertSame('24/7', $setting->pickup_hours);
        $this->assertSame('example@example.com', $setting->contact_email);
        $this->assertSame('612 117 7002', $setting->contact_phone);

        $this->assertTrue($setting->is_demo);
        $this->assertIsBool($setting->is_demo);

        $this->assertSame(1, $setting->version);
        $this->assertIsInt($setting->version);
    }

    /**
     * Test repeated calls to current return the same singleton row.
     */
    public function test_repeated_calls_to_current_maintain_single_row(): void
    {
        $first = StoreSetting::current();
        $second = StoreSetting::current();

        $this->assertSame(1, $first->id);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, StoreSetting::count());
    }

    /**
     * Test factory persisting alternative configuration returns same PK1 and not config defaults.
     */
    public function test_factory_persisting_alternative_configuration_returns_same_pk1_and_not_config_defaults(): void
    {
        $persisted = StoreSetting::factory()->create([
            'currency' => 'USD',
            'country' => 'US',
            'public_markup_basis_points' => 4500,
            'installer_markup_basis_points' => 2000,
            'tax_basis_points' => 825,
            'is_demo' => false,
            'version' => 2,
        ]);

        $this->assertSame(1, $persisted->id);

        $current = StoreSetting::current();

        $this->assertSame(1, $current->id);
        $this->assertSame('USD', $current->currency);
        $this->assertNotSame(config('store.defaults.currency'), $current->currency);
        $this->assertSame(4500, $current->public_markup_basis_points);
        $this->assertNotSame(config('store.defaults.public_markup_basis_points'), $current->public_markup_basis_points);
        $this->assertFalse($current->is_demo);
        $this->assertSame(2, $current->version);
        $this->assertSame(1, StoreSetting::count());
    }

    /**
     * Test configuration change before first current call is persisted.
     */
    public function test_config_defaults_change_before_first_current_is_persisted(): void
    {
        config([
            'store.defaults.currency' => 'EUR',
            'store.defaults.public_markup_basis_points' => 2500,
            'store.defaults.is_demo' => false,
        ]);

        $setting = StoreSetting::current();

        $this->assertSame('EUR', $setting->currency);
        $this->assertSame(2500, $setting->public_markup_basis_points);
        $this->assertFalse($setting->is_demo);

        $this->assertDatabaseHas('store_settings', [
            'id' => 1,
            'currency' => 'EUR',
            'public_markup_basis_points' => 2500,
        ]);
    }

    /**
     * Test configuration change after row exists does not alter persisted data.
     */
    public function test_config_change_after_row_creation_does_not_alter_persisted(): void
    {
        $initial = StoreSetting::current();
        $this->assertSame('MXN', $initial->currency);

        config(['store.defaults.currency' => 'CAD']);

        $retrieved = StoreSetting::current();
        $this->assertSame('MXN', $retrieved->currency);
        $this->assertNotSame('CAD', $retrieved->currency);

        $this->assertSame('MXN', $initial->fresh()->currency);
    }

    /**
     * Test PriceTier and UserRole enums have TitleCase cases and expected values.
     */
    public function test_enums_have_title_case_cases_and_expected_values(): void
    {
        $this->assertSame('public', PriceTier::Public->value);
        $this->assertSame('installer', PriceTier::Installer->value);
        $this->assertSame(['public', 'installer'], array_column(PriceTier::cases(), 'value'));

        $this->assertSame('administrator', UserRole::Administrator->value);
        $this->assertSame('installer', UserRole::Installer->value);
        $this->assertSame(['administrator', 'installer'], array_column(UserRole::cases(), 'value'));
    }

    /**
     * Test settings changes increment version progressively and create audit entries with actor and old/new values.
     */
    public function test_settings_changes_increment_version(): void
    {
        $actor1 = User::factory()->create();
        $actor2 = User::factory()->create();

        $stale1 = StoreSetting::current();
        $stale2 = StoreSetting::current();
        $this->assertSame(1, $stale1->version);
        $this->assertSame(1, $stale2->version);

        $updated1 = StoreSetting::updateCurrent(['tax_basis_points' => 1800], $actor1);

        $this->assertSame(2, $updated1->version);
        $this->assertSame(1800, $updated1->tax_basis_points);

        $audit1 = AuditEntry::latest('id')->first();
        $this->assertNotNull($audit1);
        $this->assertSame($actor1->id, $audit1->actor_id);
        $this->assertSame('store_settings.updated', $audit1->action);
        $this->assertSame('store_setting', $audit1->entity_type);
        $this->assertSame(1, $audit1->entity_id);
        $this->assertSame([
            'tax_basis_points' => [
                'old' => 1600,
                'new' => 1800,
            ],
        ], $audit1->changes);

        $updated2 = StoreSetting::updateCurrent([
            'tax_basis_points' => 2100,
            'currency' => 'USD',
        ], $actor2);

        $this->assertSame(3, $updated2->version);
        $this->assertSame(2100, $updated2->tax_basis_points);
        $this->assertSame('USD', $updated2->currency);

        $audit2 = AuditEntry::where('id', '>', $audit1->id)->first();
        $this->assertNotNull($audit2);
        $this->assertSame($actor2->id, $audit2->actor_id);
        $this->assertSame([
            'tax_basis_points' => [
                'old' => 1800,
                'new' => 2100,
            ],
            'currency' => [
                'old' => 'MXN',
                'new' => 'USD',
            ],
        ], $audit2->changes);

        $this->assertSame(2, AuditEntry::count());
    }

    /**
     * Test no-op updates do not increment version or create audit entries.
     */
    public function test_no_op_changes_do_not_increment_version(): void
    {
        $initial = StoreSetting::current();
        $this->assertSame(1, $initial->version);

        $result = StoreSetting::updateCurrent([
            'tax_basis_points' => 1600,
            'currency' => 'MXN',
        ]);

        $this->assertSame(1, $result->version);
        $this->assertSame(1, StoreSetting::current()->version);
        $this->assertSame(0, AuditEntry::count());
    }

    /**
     * Test transaction rollback when audit entry creation fails.
     */
    public function test_transaction_rollback_when_audit_entry_creation_fails(): void
    {
        StoreSetting::current();

        AuditEntry::creating(function () {
            throw new RuntimeException('Simulated audit entry creation failure');
        });

        try {
            StoreSetting::updateCurrent(['public_markup_basis_points' => 4500]);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated audit entry creation failure', $e->getMessage());
        } finally {
            AuditEntry::flushEventListeners();
        }

        $current = StoreSetting::current();
        $this->assertSame(1, $current->version);
        $this->assertSame(3000, $current->public_markup_basis_points);
        $this->assertSame(0, AuditEntry::count());
    }

    /**
     * Test unknown fields, id, and version cannot be modified and trigger InvalidArgumentException.
     */
    public function test_unknown_fields_id_and_version_are_rejected_without_mutation(): void
    {
        StoreSetting::current();

        try {
            StoreSetting::updateCurrent(['unknown_field' => 'value']);
            $this->fail('Expected InvalidArgumentException for unknown field.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('unknown_field', $e->getMessage());
        }

        try {
            StoreSetting::updateCurrent(['id' => 2]);
            $this->fail('Expected InvalidArgumentException for id field.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('id', $e->getMessage());
        }

        try {
            StoreSetting::updateCurrent(['version' => 99]);
            $this->fail('Expected InvalidArgumentException for version field.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('version', $e->getMessage());
        }

        $current = StoreSetting::current();
        $this->assertSame(1, $current->id);
        $this->assertSame(1, $current->version);
        $this->assertSame(0, AuditEntry::count());
    }

    /**
     * Test private contact and pickup changes never leak PII into audit entries while numeric fields record values.
     */
    public function test_private_contact_changes_do_not_leak_pii_in_audit_json(): void
    {
        StoreSetting::current();

        StoreSetting::updateCurrent([
            'public_markup_basis_points' => 3500,
            'pickup_name' => 'Sucursal Centro Real',
            'pickup_address' => 'Av. Insurgentes 1234, CDMX',
            'pickup_hours' => 'Lun-Vie 9:00 - 18:00',
            'contact_email' => 'private-contact@example.test',
            'contact_phone' => '+525512345678',
        ]);

        $audit = AuditEntry::latest('id')->first();
        $this->assertNotNull($audit);

        $this->assertSame([
            'old' => 3000,
            'new' => 3500,
        ], $audit->changes['public_markup_basis_points']);

        $this->assertSame(['changed' => true], $audit->changes['pickup_name']);
        $this->assertSame(['changed' => true], $audit->changes['pickup_address']);
        $this->assertSame(['changed' => true], $audit->changes['pickup_hours']);
        $this->assertSame(['changed' => true], $audit->changes['contact_email']);
        $this->assertSame(['changed' => true], $audit->changes['contact_phone']);

        $encodedJson = json_encode($audit->changes, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Sucursal Centro Real', $encodedJson);
        $this->assertStringNotContainsString('Av. Insurgentes', $encodedJson);
        $this->assertStringNotContainsString('private-contact@example.test', $encodedJson);
        $this->assertStringNotContainsString('+525512345678', $encodedJson);

        $setting = StoreSetting::current();
        $this->assertSame(3500, $setting->public_markup_basis_points);
        $this->assertSame('Sucursal Centro Real', $setting->pickup_name);
        $this->assertSame('private-contact@example.test', $setting->contact_email);
    }

    /**
     * Test demo database seeding is refused in production environment.
     */
    public function test_demo_seeding_is_refused_in_production(): void
    {
        $originalEnv = $this->app['env'];

        try {
            $this->app->detectEnvironment(fn (): string => 'production');

            $seeder = new DatabaseSeeder;
            $seeder->run();
            $this->fail('Production must refuse demonstration seeding.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Database seeding is refused in production.', $exception->getMessage());
        } finally {
            $this->app->detectEnvironment(fn (): string => $originalEnv);
        }

        $this->assertDatabaseCount('store_settings', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('audit_entries', 0);
    }

    /**
     * Test local database seeding is idempotent, seeds StoreSetting, and creates zero users.
     */
    public function test_local_seeder_is_idempotent_creates_only_settings_and_zero_users(): void
    {
        $seeder = new DatabaseSeeder;

        $seeder->run();

        $this->assertSame(1, StoreSetting::count());
        $this->assertSame(0, User::count());

        $seeder->run();

        $this->assertSame(1, StoreSetting::count());
        $this->assertSame(0, User::count());
    }
}
