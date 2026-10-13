<?php

namespace App\Services;

use App\Data\CartLineData;
use App\Data\ContactData;
use App\Data\QuoteData;
use App\Data\VerifiedPaymentResult;
use App\Enums\CouponUseStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PriceTier;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\CouponUseHistory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

class CouponReservationService
{
    public function resolveCode(string $code, bool $lock = false): Coupon
    {
        $code = strtoupper(trim($code));
        if (preg_match('/\A[A-Z0-9_-]{1,32}\z/', $code) !== 1) {
            $this->reject('Revisa el código del cupón.');
        }
        $query = Coupon::query()->where('code', $code);
        if ($lock) {
            $query->lockForUpdate();
        }
        $coupon = $query->first();
        if ($coupon === null) {
            $this->reject('El cupón no está disponible. Revisa el total sin cupón.');
        }

        return $coupon;
    }

    /** @param list<CartLineData> $lines */
    public function preview(array $lines, PriceTier $tier, string $code, string $canonical): QuoteData
    {
        $coupon = $this->resolveCode($code);
        $use = CouponUse::query()->where('coupon_id', $coupon->id)->where('phone_canonical', $canonical)->first();
        if ($use !== null && $use->status !== CouponUseStatus::Free) {
            $this->reject($use->status === CouponUseStatus::Consumed
                ? 'Este cupón ya fue usado por el número de contacto. Revisa el total sin cupón.'
                : 'El cupón está reservado. Consulta o reanuda tu compra autorizada; no se puede reservar otra.');
        }
        if (CouponUse::query()->where('coupon_id', $coupon->id)->whereIn('status', [CouponUseStatus::Reserved, CouponUseStatus::Consumed])->count() >= $coupon->max_uses) {
            $this->reject('El cupón alcanzó su límite. Revisa el total sin cupón.');
        }
        $quote = app(PricingService::class)->quote($lines, $tier, $coupon);
        if ($quote->discountCents < 1) {
            $this->reject('El cupón no aplica a esta compra. Revisa el total sin cupón.');
        }

        return $quote;
    }

    public function reserve(Coupon $coupon, ContactData $contact, Order $order, PaymentAttempt $attempt): CouponUse
    {
        $this->requireTransaction();
        $coupon = Coupon::query()->whereKey($coupon->id)->lockForUpdate()->firstOrFail();
        $phone = app(PhoneNormalizer::class)->normalize($contact->phoneOriginal, 'MX');
        if ($phone !== $contact->phoneCanonical || $phone !== $order->phone_canonical || $attempt->order_id !== $order->id
            || ! $coupon->is_active || $coupon->currency !== $order->currency
            || ($coupon->starts_at !== null && now()->lt($coupon->starts_at))
            || ($coupon->ends_at !== null && now()->gte($coupon->ends_at))) {
            $this->reject('El cupón ya no es válido para esta compra. Revisa el total sin cupón.');
        }
        $use = CouponUse::query()->where('coupon_id', $coupon->id)->where('phone_canonical', $phone)->lockForUpdate()->first();
        if ($use !== null && $use->status === CouponUseStatus::Reserved && $use->payment_attempt_id === $attempt->id && $use->order_id === $order->id) {
            return $use;
        }
        if ($use !== null && $use->status !== CouponUseStatus::Free) {
            $this->reject($use->status === CouponUseStatus::Consumed
                ? 'Este cupón ya fue usado por el número de contacto. Revisa el total sin cupón.'
                : 'El cupón está reservado en otro intento. Consulta o reanuda la compra autorizada.');
        }
        if ($this->occupied($coupon) >= $coupon->max_uses) {
            $this->reject('El cupón alcanzó su límite de usos. Revisa el total sin cupón.');
        }
        $use ??= new CouponUse;
        $previous = $use->exists ? $use->status : CouponUseStatus::Free;
        $use->forceFill(['coupon_id' => $coupon->id, 'phone_canonical' => $phone, 'status' => CouponUseStatus::Reserved,
            'order_id' => $order->id, 'payment_attempt_id' => $attempt->id, 'payment_id' => null,
            'reserved_at' => now(), 'consumed_at' => null, 'released_at' => null]);
        $this->save($use);
        $currentOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
        $currentAttempt = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
        if ($currentAttempt->first_sent_at !== null || $currentAttempt->stripe_session_id !== null
            || $currentAttempt->status !== PaymentAttemptStatus::Creating || $currentOrder->coupon_id !== null) {
            $this->reject('La reserva debe crearse con el pedido antes de contactar al proveedor.');
        }
        $currentOrder->attachCoupon($use, $currentOrder->coupon_snapshot ?? app(CouponEvaluator::class)->snapshot($coupon));
        $order->refresh();
        $this->history($use, $previous);

        return $use;
    }

    public function occupied(Coupon $coupon): int
    {
        return CouponUse::query()->where('coupon_id', $coupon->id)->whereIn('status', [CouponUseStatus::Reserved, CouponUseStatus::Consumed])->lockForUpdate()->get(['id'])->count();
    }

    public function lockForAttempt(PaymentAttempt $attempt): ?CouponUse
    {
        $this->requireTransaction();
        $order = $attempt->order;
        if ($order->coupon_id !== null) {
            Coupon::query()->whereKey($order->coupon_id)->lockForUpdate()->firstOrFail();

            return CouponUse::query()->whereKey($order->coupon_use_id)->lockForUpdate()->firstOrFail();
        }
        /**
         * Without a locator in the supplied order, acquire current coupon locks
         * in ID order before rights; a consistent read may miss a reservation
         * committed after an outer transaction established its snapshot.
         */
        Coupon::query()->orderBy('id')->lockForUpdate()->get(['id']);

        return CouponUse::query()->where('payment_attempt_id', $attempt->id)->lockForUpdate()->first();
    }

    public function consume(CouponUse $use, Payment $payment): void
    {
        $use = $this->lock($use);
        if ($use->status === CouponUseStatus::Consumed && $use->payment_id === $payment->id) {
            return;
        }
        if ($use->status !== CouponUseStatus::Reserved || $use->payment_attempt_id !== (int) $payment->payment_attempt_id
            || $use->order_id !== (int) $payment->order_id) {
            $this->reject('El pago no corresponde a una reserva vigente.');
        }
        $previous = $use->status;
        $use->forceFill(['status' => CouponUseStatus::Consumed, 'payment_id' => $payment->id, 'consumed_at' => now()]);
        $this->save($use);
        $this->history($use, $previous, $payment->evidence_id);
    }

    public function release(CouponUse $use, VerifiedPaymentResult $evidence): void
    {
        $use = $this->lock($use);
        $order = Order::query()->whereKey($use->order_id)->lockForUpdate()->firstOrFail();
        $attempt = PaymentAttempt::query()->whereKey($use->payment_attempt_id)->lockForUpdate()->firstOrFail();
        $expired = $evidence->state === 'expired' && $evidence->source === 'stripe_api';
        $neverSent = $evidence->state === 'failed' && $evidence->source === 'local_definitive'
            && $attempt->status === PaymentAttemptStatus::Failed && $attempt->first_sent_at === null
            && $attempt->stripe_session_id === null && $evidence->sessionId === null
            && $evidence->paymentIntentId === null && $evidence->chargeId === null;
        $rejected = $evidence->state === 'failed' && $evidence->source === 'stripe_create_rejection'
            && $attempt->status === PaymentAttemptStatus::Failed && $attempt->last_error === 'definitive_creation_rejection'
            && $attempt->stripe_session_id === null && $evidence->sessionId === null
            && $evidence->paymentIntentId === null && $evidence->chargeId === null;
        if ((! $expired && ! $neverSent && ! $rejected) || $evidence->problems !== []
            || $evidence->attemptReference !== $attempt->reference || $evidence->orderReference !== $order->reference
            || $evidence->accountId !== config('services.stripe.account_id') || $evidence->livemode !== (bool) config('services.stripe.livemode')
            || $evidence->currency !== $attempt->currency || $evidence->amountCents !== $attempt->amount_cents
            || ($attempt->stripe_session_id !== null && $evidence->sessionId !== $attempt->stripe_session_id)) {
            $this->reject('La reserva necesita expiración remota sin pago o un fallo definitivo antes de contactar al proveedor.');
        }
        if ($use->status !== CouponUseStatus::Reserved || Payment::query()->where('order_id', $order->id)->exists()) {
            return;
        }
        $previous = $use->status;
        $use->forceFill(['status' => CouponUseStatus::Free, 'released_at' => now()]);
        $this->save($use);
        $this->history($use, $previous, $evidence->evidenceId);
    }

    private function lock(CouponUse $use): CouponUse
    {
        $this->requireTransaction();
        Coupon::query()->whereKey($use->coupon_id)->lockForUpdate()->firstOrFail();

        return CouponUse::query()->whereKey($use->id)->lockForUpdate()->firstOrFail();
    }

    private function history(CouponUse $use, CouponUseStatus $previous, ?string $evidence = null): void
    {
        $history = new CouponUseHistory;
        $history->forceFill(['coupon_use_id' => $use->id, 'from_status' => $previous->value, 'to_status' => $use->status->value,
            'order_id' => $use->order_id, 'payment_attempt_id' => $use->payment_attempt_id,
            'payment_id' => $use->payment_id, 'evidence_id' => $evidence]);
        $this->save($history);
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Coupon rights must change in the caller financial transaction.');
        }
    }

    private function save(Model $model): void
    {
        if (! $model->save()) {
            throw new RuntimeException('Coupon right/history persistence failed.');
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['coupon_code' => $message]);
    }
}
