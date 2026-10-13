<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Exceptions\UnknownStripeOutcome;
use App\Models\Order;
use App\Services\StripeGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class EmbeddedCheckoutController extends Controller
{
    public function __invoke(Order $order, StripeGateway $gateway): JsonResponse
    {
        abort_unless(Gate::allows('view', $order), 404);
        $key = config('services.stripe.publishable_key');
        if (! config('services.stripe.enabled') || ! is_string($key)
            || ! str_starts_with($key, config('services.stripe.livemode') ? 'pk_live_' : 'pk_test_')) {
            return $this->unavailable();
        }
        $attempt = $order->paymentAttempts()->latest('id')->first();
        if ($order->status !== OrderStatus::Pending || $attempt?->status !== PaymentAttemptStatus::Open
            || ! $attempt->expires_at?->isFuture() || ($attempt->checkout_parameters['ui_mode'] ?? null) !== 'embedded_page') {
            return $this->finished();
        }
        try {
            $secret = $gateway->embeddedClientSecret($attempt);
        } catch (UnknownStripeOutcome) {
            return $this->unavailable();
        }
        $fresh = $order->fresh();
        if ($secret === null || $fresh->status !== OrderStatus::Pending || ! Gate::allows('view', $fresh)
            || $attempt->fresh()->status !== PaymentAttemptStatus::Open
            || $fresh->paymentAttempts()->latest('id')->value('id') !== $attempt->id) {
            return $this->finished();
        }

        return response()->json(['clientSecret' => $secret])->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function unavailable(): JsonResponse
    {
        return response()->json(['message' => 'No pudimos cargar el formulario de pago. Intenta cargarlo de nuevo en unos momentos.'], 503)
            ->header('Cache-Control', 'private, no-store');
    }

    private function finished(): JsonResponse
    {
        return response()->json(['message' => 'Este intento ya no admite un pago. Actualiza el estado del pedido.'], 409)
            ->header('Cache-Control', 'private, no-store');
    }
}
