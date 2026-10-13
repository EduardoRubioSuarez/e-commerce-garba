<?php

namespace Tests\Feature;

use App\Data\CartLineData;
use App\Data\ContactData;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PriceTier;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\PaymentAttempt;
use App\Models\PriceOverride;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\PricingService;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class OrderCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function contact(): ContactData
    {
        return new ContactData('Persona de prueba', 'cliente@example.test', '+52 614 123 4567', '+526141234567');
    }

    /** @return list<CartLineData> */
    private function lines(): array
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);

        return [new CartLineData($product->id, 1)];
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected checkout validation to reject this confirmation.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('order_lines', 0);
            $this->assertDatabaseCount('payment_attempts', 0);
        }
    }

    public function test_accepted_quote_creates_pending_snapshot_and_single_payment_attempt(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $lines = [new CartLineData($product->id, 1)];
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $contact = new ContactData('Persona de prueba', 'cliente@example.test', '+52 614 123 4567', '+526141234567');
        $attempt = app(CheckoutService::class)->start($lines, $contact, null, $quote->fingerprint, (string) Str::uuid());

        $this->assertSame('creating', $attempt->status->value);
        $this->assertSame('pending', $attempt->order->status->value);
        $this->assertSame(150800, $attempt->order->total_cents);
        $this->assertSame(130000, $attempt->order->subtotal_cents);
        $this->assertSame(20800, $attempt->order->tax_cents);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_lines', 1);
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_stale_fingerprint_requires_review_without_persisting_order(): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        Product::findOrFail($lines[0]->productId)->update(['base_cost_cents' => 200000]);
        $this->assertRejected(fn () => app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid()));
    }

    public function test_double_submission_returns_original_attempt_after_catalog_and_settings_change(): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $key = (string) Str::uuid();
        $first = app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, $key);
        Product::findOrFail($lines[0]->productId)->update(['base_cost_cents' => 200000, 'is_active' => false, 'name' => 'Nombre cambiado']);
        StoreSetting::updateCurrent(['tax_basis_points' => 800]);
        $second = app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, $key);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(150800, $second->order->total_cents);
        $this->assertSame(20800, $second->order->tax_cents);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_active_installer_receives_private_tariff_from_persisted_account(): void
    {
        $lines = $this->lines();
        $actor = User::factory()->installer()->create();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Installer);
        $attempt = app(CheckoutService::class)->start($lines, $this->contact(), $actor, $quote->fingerprint, (string) Str::uuid());
        $this->assertSame(127600, $attempt->order->total_cents);
        $this->assertSame(PriceTier::Installer, $attempt->order->tier);
    }

    public function test_unsaved_actor_role_change_cannot_obtain_installer_price(): void
    {
        $lines = $this->lines();
        $actor = User::factory()->administrator()->create();
        $actor->role = UserRole::Installer;
        $quote = app(PricingService::class)->quote($lines, PriceTier::Installer);
        $this->assertRejected(fn () => app(CheckoutService::class)->start($lines, $this->contact(), $actor, $quote->fingerprint, (string) Str::uuid()));
    }

    public function test_account_suspended_after_review_cannot_create_order(): void
    {
        $lines = $this->lines();
        $actor = User::factory()->installer()->create();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Installer);
        User::whereKey($actor->id)->update(['suspended_at' => now()]);
        $this->assertRejected(fn () => app(CheckoutService::class)->start($lines, $this->contact(), $actor, $quote->fingerprint, (string) Str::uuid()));
    }

    public function test_unpublished_product_cannot_be_checked_out(): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        Product::whereKey($lines[0]->productId)->update(['is_published' => false]);
        $this->assertRejected(fn () => app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid()));
    }

    public function test_minimum_charge_is_checked_before_persisting_order(): void
    {
        $lines = $this->lines();
        config(['store.checkout.minimum_charge_cents' => 150801]);
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $this->assertRejected(fn () => app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid()));
    }

    public function test_empty_cart_cannot_create_order(): void
    {
        $quote = app(PricingService::class)->quote([], PriceTier::Public);
        $this->assertRejected(fn () => app(CheckoutService::class)->start([], $this->contact(), null, $quote->fingerprint, (string) Str::uuid()));
    }

    public function test_amount_above_gateway_capacity_is_rejected_without_persistence(): void
    {
        $lines = $this->lines();
        $lines = [new CartLineData($lines[0]->productId, 1000)];
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $this->assertRejected(fn () => app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid()));
    }

    #[DataProvider('conflictingConfirmations')]
    public function test_submission_key_cannot_be_reused_for_different_confirmation(string $changed): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $key = (string) Str::uuid();
        app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, $key);
        $contact = $this->contact();
        $actor = null;
        $fingerprint = $quote->fingerprint;
        if ($changed === 'contact') {
            $contact = new ContactData('Otra persona', 'otro@example.test', '+52 614 123 4567', '+526141234567');
        } elseif ($changed === 'actor') {
            $actor = User::factory()->administrator()->create();
        } elseif ($changed === 'quantity') {
            $lines = [new CartLineData($lines[0]->productId, 2)];
        } else {
            $fingerprint = str_repeat('0', 64);
        }
        try {
            app(CheckoutService::class)->start($lines, $contact, $actor, $fingerprint, $key);
            $this->fail('A confirmation key must not authorize a different confirmation.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 1);
            $this->assertDatabaseCount('payment_attempts', 1);
        }
    }

    /** @return list<array{string}> */
    public static function conflictingConfirmations(): array
    {
        return [['contact'], ['actor'], ['quantity'], ['fingerprint']];
    }

    public function test_contact_and_line_amounts_remain_original_after_commercial_changes(): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $attempt = app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid());
        $originalName = Product::findOrFail($lines[0]->productId)->name;
        Product::whereKey($lines[0]->productId)->update(['name' => 'Nuevo nombre', 'base_cost_cents' => 200000]);
        StoreSetting::updateCurrent(['tax_basis_points' => 800]);
        $order = $attempt->order->fresh();
        $line = $order->lines->sole();
        $this->assertSame('Persona de prueba', $order->contact_name);
        $this->assertSame('+52 614 123 4567', $order->phone_original);
        $this->assertSame('+526141234567', $order->phone_canonical);
        $this->assertSame($originalName, $line->name);
        $this->assertSame(130000, $line->unit_base_cents);
        $this->assertSame(130000, $line->discounted_base_cents);
        $this->assertSame(0, $line->discount_cents);
        $this->assertSame(1600, $line->tax_basis_points);
        $this->assertSame(150800, $line->total_cents);
        $this->assertSame(150800, $order->total_cents);
        $this->assertSame(150800, $attempt->fresh()->amount_cents);
    }

    public function test_attempt_insert_failure_rolls_back_order_and_lines(): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        PaymentAttempt::creating(fn () => throw new RuntimeException('Simulated attempt insert failure'));
        try {
            app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid());
            $this->fail('Insert failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated attempt insert failure', $exception->getMessage());
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('order_lines', 0);
            $this->assertDatabaseCount('payment_attempts', 0);
        } finally {
            PaymentAttempt::flushEventListeners();
            PaymentAttempt::clearBootedModels();
        }
    }

    public function test_canceled_attempt_insert_does_not_leave_partial_order(): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        PaymentAttempt::creating(fn (): bool => false);
        try {
            app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid());
            $this->fail('Canceled insert must abort transaction.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('order_lines', 0);
            $this->assertDatabaseCount('payment_attempts', 0);
        } finally {
            PaymentAttempt::flushEventListeners();
            PaymentAttempt::clearBootedModels();
        }
    }

    public function test_snapshot_money_and_contact_cannot_be_overwritten_on_persisted_order(): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $order = app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid())->order;
        try {
            $order->forceFill(['total_cents' => 1, 'contact_name' => 'Otra persona'])->save();
            $this->fail('Order snapshot must be immutable.');
        } catch (\LogicException) {
            $this->assertSame(150800, $order->fresh()->total_cents);
            $this->assertSame('Persona de prueba', $order->fresh()->contact_name);
        }
    }

    public function test_snapshot_line_cannot_be_overwritten(): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $line = app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid())->order->lines->sole();
        try {
            $line->forceFill(['quantity' => 100])->save();
            $this->fail('Line snapshot must be immutable.');
        } catch (\LogicException) {
            $this->assertSame(1, $line->fresh()->quantity);
        }
    }

    public function test_model_serialization_does_not_expose_contact_or_confirmation_keys(): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $key = (string) Str::uuid();
        $attempt = app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, $key);
        $json = $attempt->load('order.lines')->toJson();
        foreach ([$key, $attempt->idempotency_key, $quote->fingerprint, 'cliente@example.test', '+526141234567', 'base_cost_cents'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
    }

    public function test_minimum_fixture_accepts_exactly_1000_cents(): void
    {
        StoreSetting::updateCurrent(['public_markup_basis_points' => 0, 'tax_basis_points' => 0]);
        $product = Product::factory()->published()->create(['base_cost_cents' => 1000]);
        $lines = [new CartLineData($product->id, 1)];
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $attempt = app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid());
        $this->assertSame(1000, $attempt->amount_cents);
    }

    public function test_minimum_fixture_rejects_999_cents(): void
    {
        StoreSetting::updateCurrent(['public_markup_basis_points' => 0, 'tax_basis_points' => 0]);
        $product = Product::factory()->published()->create(['base_cost_cents' => 999]);
        $lines = [new CartLineData($product->id, 1)];
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $this->assertRejected(fn () => app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid()));
    }

    #[DataProvider('invalidConfirmations')]
    public function test_malformed_confirmation_is_rejected_before_persistence(string $fingerprint, string $key): void
    {
        $lines = $this->lines();
        $this->assertRejected(fn () => app(CheckoutService::class)->start($lines, $this->contact(), null, $fingerprint, $key));
    }

    /** @return list<array{string,string}> */
    public static function invalidConfirmations(): array
    {
        return [['', ''], [str_repeat('0', 64), 'browser-invented'], ['invalid', '123e4567-e89b-12d3-a456-426614174000'], [str_repeat('g', 64), '123e4567-e89b-12d3-a456-426614174000']];
    }

    public function test_duplicate_cart_lines_are_deduplicated_before_snapshot(): void
    {
        $lines = $this->lines();
        $lines[] = new CartLineData($lines[0]->productId, 2);
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $key = (string) Str::uuid();
        $attempt = app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, $key);
        $this->assertSame(452400, $attempt->order->total_cents);
        $this->assertSame(3, $attempt->order->lines->sole()->quantity);
        $replay = app(CheckoutService::class)->start([new CartLineData($lines[0]->productId, 3)], $this->contact(), null, $quote->fingerprint, $key);
        $this->assertSame($attempt->id, $replay->id);
    }

    public function test_quantity_overflow_is_rejected_without_persistence(): void
    {
        $lines = $this->lines();
        $invalid = [new CartLineData($lines[0]->productId, PHP_INT_MAX), new CartLineData($lines[0]->productId, 1)];
        $this->assertRejected(fn () => app(CheckoutService::class)->start($invalid, $this->contact(), null, str_repeat('0', 64), (string) Str::uuid()));
    }

    public function test_financial_status_can_change_without_mutating_snapshot(): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $attempt = app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid());
        $attempt->forceFill(['status' => PaymentAttemptStatus::Open])->save();
        $attempt->order->forceFill(['status' => OrderStatus::Paid])->save();
        $this->assertSame('paid', $attempt->order->fresh()->status->value);
        $this->assertSame(150800, $attempt->order->fresh()->total_cents);
        $this->assertSame('open', $attempt->fresh()->status->value);
    }

    public function test_private_models_reject_request_mass_assignment(): void
    {
        foreach ([new Order, new OrderLine, new PaymentAttempt] as $model) {
            try {
                $model->fill(['total_cents' => 1, 'status' => 'paid', 'user_id' => 99, 'submission_key' => 'fake']);
            } catch (MassAssignmentException) {
                $this->assertSame([], $model->getAttributes());

                continue;
            }
            $this->assertSame([], $model->getAttributes());
        }
    }

    public function test_database_requires_unique_confirmation_key(): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $key = (string) Str::uuid();
        $attempt = app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, $key);
        $duplicate = $attempt->replicate(['reference', 'idempotency_key']);
        $duplicate->forceFill(['reference' => (string) Str::uuid(), 'idempotency_key' => (string) Str::uuid()]);
        $this->expectException(UniqueConstraintViolationException::class);
        $duplicate->save();
    }

    public function test_checkout_does_not_send_remote_requests(): void
    {
        Http::preventStrayRequests();
        Mail::fake();
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid());
        Http::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    public function test_replay_from_suspended_installer_is_rejected(): void
    {
        $lines = $this->lines();
        $actor = User::factory()->installer()->create();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Installer);
        $key = (string) Str::uuid();
        app(CheckoutService::class)->start($lines, $this->contact(), $actor, $quote->fingerprint, $key);
        User::whereKey($actor->id)->update(['suspended_at' => now()]);
        try {
            app(CheckoutService::class)->start($lines, $this->contact(), $actor, $quote->fingerprint, $key);
            $this->fail('Suspended accounts cannot resume checkout.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 1);
            $this->assertDatabaseCount('payment_attempts', 1);
        }
    }

    public function test_more_than_one_payment_attempt_can_reference_same_order(): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $first = app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid());
        $next = PaymentAttempt::factory()->for($first->order)->create(['submission_key' => null]);
        $this->assertNotSame($first->id, $next->id);
        $this->assertSame(2, $first->order->paymentAttempts()->count());
    }

    public function test_unrelated_unique_constraint_failure_is_not_treated_as_double_submission(): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $first = app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid());
        PaymentAttempt::creating(function (PaymentAttempt $attempt) use ($first): void {
            $attempt->idempotency_key = $first->idempotency_key;
        });
        try {
            app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid());
            $this->fail('Unrelated unique failures must propagate.');
        } catch (UniqueConstraintViolationException) {
            $this->assertDatabaseCount('orders', 1);
            $this->assertDatabaseCount('order_lines', 1);
            $this->assertDatabaseCount('payment_attempts', 1);
        } finally {
            PaymentAttempt::flushEventListeners();
            PaymentAttempt::clearBootedModels();
        }
    }

    public function test_invalid_amount_configuration_aborts_without_partial_order(): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        config(['store.checkout.minimum_charge_cents' => '1000']);
        try {
            app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid());
            $this->fail('Invalid configuration must abort checkout.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('order_lines', 0);
            $this->assertDatabaseCount('payment_attempts', 0);
        }
    }

    public function test_stale_consistent_quote_cannot_override_current_locked_commercial_data(): void
    {
        $lines = $this->lines();
        $obsolete = app(PricingService::class)->quote($lines, PriceTier::Public);
        Product::whereKey($lines[0]->productId)->update(['base_cost_cents' => 200000]);
        $this->partialMock(PricingService::class, function (MockInterface $mock) use ($obsolete): void {
            $mock->shouldReceive('quote')->andReturn($obsolete);
        });
        $this->assertRejected(fn () => app(CheckoutService::class)->start($lines, $this->contact(), null, $obsolete->fingerprint, (string) Str::uuid()));
    }

    public function test_manual_price_and_line_rounding_are_preserved_in_order(): void
    {
        $lines = $this->lines();
        PriceOverride::factory()->create(['product_id' => $lines[0]->productId, 'tier' => PriceTier::Public, 'base_price_cents' => 101]);
        $lines = [new CartLineData($lines[0]->productId, 10)];
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $attempt = app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid());
        $line = $attempt->order->lines->sole();
        $this->assertSame('manual', $line->price_source);
        $this->assertSame(1010, $line->base_cents);
        $this->assertSame(162, $line->tax_cents);
        $this->assertSame(1172, $attempt->amount_cents);
    }

    public function test_changed_tax_settings_require_new_acceptance_before_order(): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        StoreSetting::updateCurrent(['tax_basis_points' => 800]);
        $this->assertRejected(fn () => app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid()));
    }

    #[DataProvider('maximumChargeBoundary')]
    public function test_technical_amount_limit_is_enforced(int $amount, bool $allowed): void
    {
        StoreSetting::updateCurrent(['public_markup_basis_points' => 0, 'tax_basis_points' => 0]);
        $product = Product::factory()->published()->create(['base_cost_cents' => $amount]);
        $lines = [new CartLineData($product->id, 1)];
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        if (! $allowed) {
            $this->assertRejected(fn () => app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid()));

            return;
        }
        $attempt = app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid());
        $this->assertSame(99999999, $attempt->amount_cents);
    }

    /** @return list<array{int,bool}> */
    public static function maximumChargeBoundary(): array
    {
        return [[99999999, true], [100000000, false]];
    }

    public function test_invalid_canonical_phone_cannot_be_persisted_by_service(): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $contact = new ContactData('Persona', 'cliente@example.test', '+52 614 123 4567', '+525555555555');
        $this->assertRejected(fn () => app(CheckoutService::class)->start($lines, $contact, null, $quote->fingerprint, (string) Str::uuid()));
    }

    public function test_password_reset_between_request_and_transaction_rejects_old_actor(): void
    {
        $actor = User::factory()->installer()->create();
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Installer);
        User::whereKey($actor->id)->update(['auth_version' => 1]);
        $this->assertRejected(fn () => app(CheckoutService::class)->start($lines, $this->contact(), $actor, $quote->fingerprint, (string) Str::uuid()));
    }

    public function test_attempt_amount_cannot_be_changed_after_confirmation(): void
    {
        $lines = $this->lines();
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $attempt = app(CheckoutService::class)->start($lines, $this->contact(), null, $quote->fingerprint, (string) Str::uuid());
        try {
            $attempt->forceFill(['amount_cents' => 1])->save();
            $this->fail('Payment attempt money must remain frozen.');
        } catch (\LogicException) {
            $this->assertSame(150800, $attempt->fresh()->amount_cents);
        }
    }
}
