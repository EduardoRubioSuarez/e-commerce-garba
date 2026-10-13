<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\OrderLine;
use App\Services\OrderAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function show(Request $request, Order $order, OrderAccessService $access): Response|JsonResponse|RedirectResponse
    {
        if ($request->query->has('token')) {
            $token = $request->query('token');
            abort_unless(is_string($token) && $access->allows($order, $request->user(), $token), 404);

            return redirect()->route('orders.show', ['order' => $order->reference], 303)
                ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
        }
        abort_unless(Gate::allows('view', $order), 404);
        $order->load('lines');
        $attempt = $order->paymentAttempts()->latest('id')->first();
        $checkoutUrl = $order->status === OrderStatus::Pending
            && $attempt?->status === PaymentAttemptStatus::Open
            && $attempt->expires_at?->isFuture() ? $attempt->checkout_url : null;
        $embeddedCheckout = config('services.stripe.enabled') && $order->status === OrderStatus::Pending
            && $attempt?->status === PaymentAttemptStatus::Open && $attempt->expires_at?->isFuture()
            && ($attempt->checkout_parameters['ui_mode'] ?? null) === 'embedded_page';
        if ($embeddedCheckout) {
            $checkoutUrl = null;
        }
        $stripePublishableKey = config('services.stripe.publishable_key');
        if (! is_string($stripePublishableKey)
            || ! str_starts_with($stripePublishableKey, config('services.stripe.livemode') ? 'pk_live_' : 'pk_test_')) {
            $stripePublishableKey = null;
        }
        $data = [
            'reference' => $order->reference, 'status' => $order->status->value, 'currency' => $order->currency,
            'contact' => ['name' => $order->contact_name, 'email' => $order->contact_email, 'phone' => $order->phone_original],
            'subtotal_cents' => $order->subtotal_cents, 'discount_cents' => $order->discount_cents,
            'base_cents' => $order->base_cents, 'tax_cents' => $order->tax_cents, 'total_cents' => $order->total_cents,
            'lines' => $order->lines->map(function (OrderLine $line): array {
                $data = [];
                foreach (['name', 'quantity', 'unit_base_cents', 'price_source', 'base_cents', 'discount_cents', 'discounted_base_cents', 'tax_basis_points', 'tax_cents', 'total_cents'] as $field) {
                    $data[$field] = $line->getAttribute($field);
                }

                return $data;
            })->all(),
        ];
        if ($request->expectsJson()) {
            return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
        }
        $link = $request->session()->get('order_link');
        $privateLink = is_array($link) && ($link['reference'] ?? null) === $order->reference ? $link['url'] : null;

        $canResume = config('services.stripe.enabled') && $order->status === OrderStatus::Pending
            && in_array($attempt?->status, [PaymentAttemptStatus::Creating, PaymentAttemptStatus::Unknown], true);

        return response()->view('store.order-result', ['data' => $data, 'privateLink' => $privateLink, 'checkoutUrl' => $checkoutUrl,
            'canResume' => $canResume, 'embeddedCheckout' => $embeddedCheckout, 'stripePublishableKey' => $stripePublishableKey])
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
