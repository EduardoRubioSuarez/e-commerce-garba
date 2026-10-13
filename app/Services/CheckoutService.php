<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CartLineData;
use App\Data\ContactData;
use App\Data\QuoteData;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PriceTier;
use App\Enums\UserRole;
use App\Models\CouponUse;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PriceOverride;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\StripeEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OverflowException;
use RuntimeException;

class CheckoutService
{
    public function __construct(private PricingService $pricing, private PhoneNormalizer $phones) {}

    public function resolveBeforeReplacement(PaymentAttempt $attempt): void
    {
        $gateway = app(StripeGateway::class);
        $gateway->assertOutsideTransaction();
        $attempt = $attempt->fresh();
        if ($attempt->order->status === OrderStatus::Paid || $attempt->status === PaymentAttemptStatus::Paid) {
            throw ValidationException::withMessages(['checkout' => 'El pedido anterior ya tiene un pago. Consulta su resultado.']);
        }
        $session = null;
        if ($attempt->stripe_session_id !== null) {
            $session = $gateway->expireCheckout($attempt->stripe_session_id);
        } elseif ($attempt->first_sent_at !== null && $attempt->status !== PaymentAttemptStatus::Failed) {
            $session = $gateway->findCheckout($attempt);
            if ($session === null) {
                throw ValidationException::withMessages(['checkout' => 'El intento anterior aún requiere conciliación.']);
            }
            $session = $gateway->expireCheckout($session->id);
        }
        if ($session !== null) {
            $gateway->validateSession($attempt, $session);
            $gateway->remember($attempt, $session);
            if ($session->status !== 'expired' || $session->paymentStatus !== 'unpaid') {
                throw ValidationException::withMessages(['checkout' => 'El intento anterior puede tener un pago. Consulta su resultado.']);
            }
        }
        if ($attempt->order->coupon_id !== null) {
            if ($session === null) {
                throw ValidationException::withMessages(['checkout' => 'El intento con cupón requiere una resolución verificada antes de reemplazarlo.']);
            }
            $event = new StripeEvent;
            $event->forceFill(['stripe_event_id' => 'replacement:'.$attempt->reference,
                'type' => 'checkout.session.expired',
                'payload' => ['api_version' => config('services.stripe.api_version'), 'livemode' => config('services.stripe.livemode'),
                    'created' => now()->timestamp, 'data' => ['object' => ['id' => $session->id]]]]);
            $result = app(PaymentResultResolver::class)->resolve($event);
            app(PaymentResultApplier::class)->apply($attempt, $result);
            if ($attempt->order->fresh()->status !== OrderStatus::ClosedUnpaid) {
                throw ValidationException::withMessages(['checkout' => 'El intento anterior requiere revisión.']);
            }

            return;
        }
        DB::transaction(function () use ($attempt, $session): void {
            $order = Order::query()->whereKey($attempt->order_id)->lockForUpdate()->firstOrFail();
            $current = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($order->status === OrderStatus::Paid || $current->status === PaymentAttemptStatus::Paid
                || Payment::query()->where('order_id', $order->id)->exists()
                || PaymentAttempt::query()->where('order_id', $order->id)->whereKeyNot($current->id)->whereIn('status', [PaymentAttemptStatus::Creating, PaymentAttemptStatus::Open, PaymentAttemptStatus::Unknown, PaymentAttemptStatus::Paid])->exists()
                || ($session === null && $current->first_sent_at !== null && $current->status !== PaymentAttemptStatus::Failed)) {
                throw ValidationException::withMessages(['checkout' => 'El intento anterior requiere revisión.']);
            }
            $current->status = $session === null ? PaymentAttemptStatus::Failed : PaymentAttemptStatus::Expired;
            $order->status = OrderStatus::ClosedUnpaid;
            if (! $current->save() || ! $order->save()) {
                throw new RuntimeException('Could not close unpaid order.');
            }
        }, 3);
    }

    /** @param list<CartLineData> $lines */
    public function start(array $lines, ContactData $contact, ?User $actor, string $acceptedFingerprint, string $submissionKey, ?string $couponCode = null): PaymentAttempt
    {
        Validator::make([
            'submission_key' => $submissionKey,
            'accepted_fingerprint' => $acceptedFingerprint,
        ], [
            'submission_key' => ['required', 'uuid'],
            'accepted_fingerprint' => ['required', 'regex:/\A[a-f0-9]{64}\z/'],
        ])->validate();
        $submissionKey = strtolower($submissionKey);
        $couponCode = $couponCode === null || trim($couponCode) === '' ? null : strtoupper(trim($couponCode));
        $canonical = $this->canonicalLines($lines);
        $this->validateContact($contact);
        $confirmation = [
            'lines' => array_map(fn (CartLineData $line): array => $line->toArray(), $canonical),
            'contact' => [$contact->name, $contact->email, $contact->phoneOriginal, $contact->phoneCanonical],
            'user_id' => $actor?->getKey(),
            'fingerprint' => $acceptedFingerprint,
        ];
        if ($couponCode !== null) {
            $confirmation['coupon_code'] = $couponCode;
        }
        $confirmationHash = hash('sha256', json_encode($confirmation, JSON_THROW_ON_ERROR));

        $existing = PaymentAttempt::query()->where('submission_key', $submissionKey)->first();
        if ($existing !== null) {
            return $this->recover($existing, $actor, $confirmationHash);
        }

        StoreSetting::current();
        $orderReference = (string) Str::uuid();
        $attemptReference = (string) Str::uuid();
        $idempotencyKey = (string) Str::uuid();

        try {
            return DB::transaction(function () use ($canonical, $contact, $actor, $acceptedFingerprint, $submissionKey, $confirmationHash, $orderReference, $attemptReference, $idempotencyKey, $couponCode): PaymentAttempt {
                $owner = $this->activeActor($actor);
                $tier = $owner?->role === UserRole::Installer ? PriceTier::Installer : PriceTier::Public;
                $settings = StoreSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
                $existing = PaymentAttempt::query()->where('submission_key', $submissionKey)->lockForUpdate()->first();
                if ($existing !== null) {
                    return $this->matchingAttempt($existing, $confirmationHash);
                }
                $productIds = array_map(fn (CartLineData $line): int => $line->productId, $canonical);
                $products = Product::query()->whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get();
                $coupon = $couponCode === null ? null : app(CouponReservationService::class)->resolveCode($couponCode, true);
                if ($coupon !== null) {
                    $selected = DB::table('coupon_product')->where('coupon_id', $coupon->id)->lockForUpdate()->pluck('product_id');
                    $coupon->setRelation('products', new Collection($selected->map(fn ($id) => (new Product)->newFromBuilder(['id' => (int) $id]))->all()));
                    CouponUse::query()->where('coupon_id', $coupon->id)->where('phone_canonical', $contact->phoneCanonical)->lockForUpdate()->first();
                }

                if ($products->count() !== count($productIds) || $products->contains(fn (Product $product): bool => ! $product->is_active || ! $product->is_published)) {
                    throw ValidationException::withMessages(['cart' => 'Un producto ya no está disponible. Revisa tu carrito.']);
                }

                try {
                    $quote = $this->lockedQuote($canonical, $products, $settings, $tier);
                    if ($coupon !== null) {
                        $quote = $this->pricing->applyCoupon($quote, $coupon);
                        if ($quote->discountCents < 1) {
                            throw ValidationException::withMessages(['coupon' => 'El cupón ya no es válido para estos artículos. Revisa el total sin cupón.']);
                        }
                    }
                } catch (InvalidArgumentException|OverflowException) {
                    throw ValidationException::withMessages(['cart' => 'No podemos calcular ese carrito. Revisa los artículos y cantidades.']);
                }
                if (! hash_equals($quote->fingerprint, $acceptedFingerprint)) {
                    throw ValidationException::withMessages(['accepted_fingerprint' => 'La cotización cambió. Revisa y acepta el total actualizado.']);
                }
                $minimum = config('store.checkout.minimum_charge_cents');
                $maximum = config('store.checkout.maximum_amount_cents');
                if (! is_int($minimum) || ! is_int($maximum) || $minimum < 1 || $maximum < $minimum || $maximum > 99999999) {
                    throw new RuntimeException('Checkout amount limits are not valid.');
                }
                if ($quote->totalCents < $minimum || $quote->totalCents > $maximum) {
                    throw ValidationException::withMessages(['cart' => 'El total está fuera de los límites de pago. Revisa tu carrito.']);
                }

                $order = new Order;
                $order->forceFill([
                    'reference' => $orderReference, 'user_id' => $owner?->id, 'status' => OrderStatus::Pending,
                    'contact_name' => $contact->name, 'contact_email' => $contact->email,
                    'phone_original' => $contact->phoneOriginal, 'phone_canonical' => $contact->phoneCanonical,
                    'tier' => $quote->tier, 'currency' => $quote->currency,
                    'subtotal_cents' => $quote->subtotalCents, 'discount_cents' => $quote->discountCents,
                    'base_cents' => $quote->baseCents, 'tax_cents' => $quote->taxCents, 'total_cents' => $quote->totalCents,
                    'settings_version' => $quote->settingsVersion, 'fingerprint' => $quote->fingerprint,
                    'coupon_snapshot' => $quote->couponSnapshot,
                ]);
                if (! $order->save()) {
                    throw new RuntimeException('Order persistence was cancelled.');
                }
                foreach ($quote->lines as $quoteLine) {
                    $line = new OrderLine;
                    $line->forceFill(['order_id' => $order->id] + $quoteLine->toArray());
                    if (! $line->save()) {
                        throw new RuntimeException('Order line persistence was cancelled.');
                    }
                }

                $attempt = new PaymentAttempt;
                $attempt->forceFill([
                    'order_id' => $order->id, 'reference' => $attemptReference, 'submission_key' => $submissionKey,
                    'confirmation_hash' => $confirmationHash, 'idempotency_key' => $idempotencyKey,
                    'status' => PaymentAttemptStatus::Creating, 'amount_cents' => $quote->totalCents, 'currency' => $quote->currency,
                ]);
                if (! $attempt->save()) {
                    throw new RuntimeException('Payment attempt persistence was cancelled.');
                }
                if ($coupon !== null) {
                    app(CouponReservationService::class)->reserve($coupon, $contact, $order, $attempt);
                }

                return $attempt->setRelation('order', $order);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if ($exception->index !== 'payment_attempts_submission_key_unique'
                && $exception->columns !== ['payment_attempts.submission_key']
                && $exception->columns !== ['submission_key']) {
                throw $exception;
            }
            $existing = PaymentAttempt::query()->where('submission_key', $submissionKey)->lockForUpdate()->first();
            if ($existing === null) {
                throw $exception;
            }

            return $this->recover($existing, $actor, $confirmationHash);
        }
    }

    /** @param list<CartLineData> $lines
     * @return list<CartLineData>
     */
    private function canonicalLines(array $lines): array
    {
        if (! array_is_list($lines) || $lines === []) {
            throw ValidationException::withMessages(['cart' => 'Agrega artículos al carrito antes de continuar.']);
        }
        $quantities = [];
        foreach ($lines as $line) {
            if (! $line instanceof CartLineData || $line->quantity < 1 || $line->productId < 1) {
                throw ValidationException::withMessages(['cart' => 'El carrito contiene un artículo o una cantidad inválida.']);
            }
            $previous = $quantities[$line->productId] ?? 0;
            if ($previous > PHP_INT_MAX - $line->quantity) {
                throw ValidationException::withMessages(['cart' => 'La cantidad supera el límite de cálculo.']);
            }
            $quantities[$line->productId] = $previous + $line->quantity;
        }
        ksort($quantities, SORT_NUMERIC);
        $canonical = [];
        foreach ($quantities as $id => $quantity) {
            $canonical[] = new CartLineData($id, $quantity);
        }

        return $canonical;
    }

    private function validateContact(ContactData $contact): void
    {
        Validator::make([
            'name' => $contact->name, 'email' => $contact->email, 'phone' => $contact->phoneOriginal,
        ], [
            'name' => ['required', 'string', 'max:150', 'regex:/\A[^\x00-\x1F\x7F]+\z/u'],
            'email' => ['required', 'string', 'max:255', 'email', 'regex:/\A[^\x00-\x1F\x7F]+\z/u'],
            'phone' => ['required', 'string', 'max:64'],
        ])->validate();
        if ($this->phones->normalize($contact->phoneOriginal, 'MX') !== $contact->phoneCanonical) {
            throw ValidationException::withMessages(['phone' => 'Revisa el teléfono de contacto.']);
        }
    }

    /**
     * @param  list<CartLineData>  $lines
     * @param  Collection<int, Product>  $products
     */
    private function lockedQuote(array $lines, Collection $products, StoreSetting $settings, PriceTier $tier): QuoteData
    {
        $overrides = PriceOverride::query()->whereIn('product_id', $products->modelKeys())
            ->orderBy('product_id')->orderBy('tier')->lockForUpdate()->get();
        $products = $products->keyBy('id');
        $quotedLines = [];
        $subtotal = 0;
        $tax = 0;
        $total = 0;
        foreach ($lines as $line) {
            $product = $products->get($line->productId);
            $product->setRelation('priceOverrides', $overrides->filter(fn (PriceOverride $override): bool => $override->product_id === $product->id));
            $quotedLine = $this->pricing->quoteLine($product, $line->quantity, $tier, $settings);
            if ($subtotal > PHP_INT_MAX - $quotedLine->baseCents
                || $tax > PHP_INT_MAX - $quotedLine->taxCents
                || $total > PHP_INT_MAX - $quotedLine->totalCents) {
                throw new OverflowException('Checkout quote total overflow.');
            }
            $subtotal += $quotedLine->baseCents;
            $tax += $quotedLine->taxCents;
            $total += $quotedLine->totalCents;
            $quotedLines[] = $quotedLine;
        }

        return new QuoteData(
            lines: $quotedLines, tier: $tier, currency: trim($settings->currency),
            subtotalCents: $subtotal, discountCents: 0, baseCents: $subtotal,
            taxCents: $tax, totalCents: $total, settingsVersion: $settings->version,
        );
    }

    private function activeActor(?User $actor): ?User
    {
        if ($actor === null) {
            return null;
        }
        $current = User::query()->whereKey($actor->getKey())->lockForUpdate()->first();
        if ($current === null || ! $current->isActive() || $current->auth_version !== $actor->auth_version) {
            throw ValidationException::withMessages(['account' => 'La cuenta no está disponible para continuar la compra.']);
        }

        return $current;
    }

    private function matchingAttempt(PaymentAttempt $attempt, string $confirmationHash): PaymentAttempt
    {
        if (! hash_equals($attempt->confirmation_hash, $confirmationHash)) {
            throw ValidationException::withMessages(['submission_key' => 'Esta confirmación corresponde a otros datos. Revisa la compra.']);
        }

        return $attempt->setRelation('order', Order::query()->whereKey($attempt->order_id)->lockForUpdate()->firstOrFail());
    }

    private function recover(PaymentAttempt $existing, ?User $actor, string $confirmationHash): PaymentAttempt
    {
        return DB::transaction(function () use ($existing, $actor, $confirmationHash): PaymentAttempt {
            $this->activeActor($actor);
            StoreSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($existing->order_id)->lockForUpdate()->firstOrFail();
            $attempt = PaymentAttempt::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
            $attempt = $this->matchingAttempt($attempt, $confirmationHash);

            return $attempt->setRelation('order', $order);
        }, 3);
    }
}
