<?php

namespace App\Http\Controllers;

use App\Data\CartLineData;
use App\Data\ContactData;
use App\Data\QuoteData;
use App\Enums\OrderStatus;
use App\Exceptions\DefinitiveStripeFailure;
use App\Exceptions\UnknownStripeOutcome;
use App\Http\Requests\StartCheckoutRequest;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\CouponReservationService;
use App\Services\OrderAccessService;
use App\Services\PricingService;
use App\Services\StorefrontService;
use App\Services\StripeGateway;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OverflowException;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart, private PricingService $pricing, private StorefrontService $storefront,
        private CheckoutService $checkout, private OrderAccessService $access,
    ) {}

    public function show(Request $request): Response|JsonResponse|RedirectResponse
    {
        $this->activeActor($request);
        $lines = $this->cart->lines();
        if ($lines === []) {
            return $this->redirect('cart.show')->with('cart_notice', 'Agrega equipos al carrito antes de continuar.');
        }
        $contact = $this->storedReview($request)['contact'] ?? [];
        if ($request->expectsJson()) {
            return response()->json(['data' => ['step' => 'contact']])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
        }

        return response()->view('store.checkout-review', ['step' => 'contact', 'contact' => $contact])
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function review(StartCheckoutRequest $request): RedirectResponse
    {
        $this->activeActor($request);
        $contact = $request->contactData();
        $this->prepareReview($request, $contact, true);

        return $this->redirect('checkout.confirmation');
    }

    public function newPurchase(Request $request, Order $order): RedirectResponse
    {
        $this->activeActor($request);
        abort_unless(Gate::allows('view', $order), 404);
        $previous = $this->storedReview($request);
        if (($previous['order_reference'] ?? null) === $order->reference) {
            $request->session()->forget('checkout.review');
        }

        return $this->redirect('cart.show')->with('cart_notice', 'Revisa los equipos y cantidades para tu nueva compra.');
    }

    public function resume(Request $request, Order $order): RedirectResponse
    {
        $this->activeActor($request);
        abort_unless(Gate::allows('view', $order), 404);
        abort_unless(config('services.stripe.enabled'), 503);
        $this->limitCheckout($request, 'requests', 10, 60);
        if ($order->status === OrderStatus::Pending) {
            try {
                app(StripeGateway::class)->createCheckout($order->paymentAttempts()->latest('id')->firstOrFail());
            } catch (UnknownStripeOutcome|DefinitiveStripeFailure) {
                $request->session()->flash('checkout_notice', 'El intento sigue pendiente de verificación. No se ha creado otro pedido.');
            }
        }

        return $this->redirect('orders.show', ['order' => $order->reference]);
    }

    public function confirmation(Request $request): Response|JsonResponse|RedirectResponse
    {
        $this->activeActor($request);
        $previous = $this->storedReview($request);
        if ($previous === null) {
            return $this->redirect('checkout.show');
        }
        if (isset($previous['order_reference'])) {
            return $this->redirect('orders.show', ['order' => $previous['order_reference']]);
        }
        [$review, $quote] = $this->prepareReview($request, $this->contactData($previous), false);
        $data = [
            'review_key' => $review['review_key'], 'fingerprint' => $review['fingerprint'],
            'contact' => ['name' => $review['contact']['name'], 'email' => $review['contact']['email'], 'phone' => $review['contact']['phone']],
            'quote' => $quote->toArray(),
            'changed' => $previous['fingerprint'] !== $review['fingerprint'],
            'coupon_code' => $review['coupon_code'] ?? null,
        ];
        if ($request->expectsJson()) {
            return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
        }

        return response()->view('store.checkout-review', ['step' => 'review', 'data' => $data])
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function start(Request $request): RedirectResponse|JsonResponse
    {
        if (config('services.stripe.enabled')) {
            $this->limitCheckout($request, 'requests', 10, 60);
        }
        $actor = $this->activeActor($request);
        $validated = $request->validate([
            'review_key' => ['required', 'uuid'],
            'accepted_fingerprint' => ['required', 'regex:/\A[a-f0-9]{64}\z/'],
            'accept_terms' => ['required', 'accepted'],
        ]);
        $review = $this->storedReview($request);
        if ($review === null || ! hash_equals($review['review_key'], $validated['review_key'])
            || $review['user_id'] !== $actor?->id) {
            throw ValidationException::withMessages(['review_key' => 'La revisión ya no corresponde a esta sesión. Revisa nuevamente tu compra.']);
        }
        if (! hash_equals($review['fingerprint'], $validated['accepted_fingerprint'])) {
            throw ValidationException::withMessages(['accepted_fingerprint' => 'Revisa y acepta la cotización vigente.']);
        }
        if (! isset($review['order_reference']) && ($review['coupon_code'] ?? null) !== $request->session()->get('cart.coupon_code')) {
            throw ValidationException::withMessages(['accepted_fingerprint' => 'El cupón cambió. Revisa y acepta el total actualizado.']);
        }
        $lines = array_map(fn (array $line): CartLineData => new CartLineData($line['product_id'], $line['quantity']), $review['lines']);
        if (! isset($review['order_reference'])) {
            $current = array_map(fn (CartLineData $line): array => $line->toArray(), $this->cart->lines());
            usort($current, fn (array $a, array $b): int => $a['product_id'] <=> $b['product_id']);
            if ($current !== $review['lines']) {
                throw ValidationException::withMessages(['accepted_fingerprint' => 'El carrito cambió. Revisa nuevamente los artículos y el total.']);
            }
        }
        if (config('services.stripe.enabled') && ! PaymentAttempt::query()->where('submission_key', $review['submission_key'])->exists()) {
            $this->limitCheckout($request, 'new-attempts', 5, 3600);
        }
        try {
            [$order, $token, $attempt] = DB::transaction(function () use ($lines, $review, $actor): array {
                $attempt = $this->checkout->start($lines, $this->contactData($review), $actor, $review['fingerprint'], $review['submission_key'], $review['coupon_code'] ?? null);
                $order = $attempt->order;
                $token = $order->token_hash === null ? $this->access->issue($order) : null;

                return [$order, $token, $attempt];
            }, 3);
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['coupon']) || isset($exception->errors()['coupon_code'])) {
                $request->session()->forget('cart.coupon_code');
                $this->prepareReview($request, $this->contactData($review), true);
                $request->session()->flash('coupon_notice', 'El cupón ya no está disponible. Revisa y acepta el total sin descuento.');
            }
            throw $exception;
        }
        if ($token !== null) {
            abort_unless($this->access->allows($order, $actor, $token), 404);
            $request->session()->flash('order_link', [
                'reference' => $order->reference, 'url' => route('orders.show', ['order' => $order->reference, 'token' => $token]),
            ]);
        } else {
            $request->session()->put('orders.access.'.$order->reference, ['token_hash' => $order->token_hash]);
        }
        if (! isset($review['order_reference'])) {
            $this->cart->clear();
        }
        $review['order_reference'] = $order->reference;
        $request->session()->put('checkout.review', $review);
        if (config('services.stripe.enabled')) {
            try {
                app(StripeGateway::class)->createCheckout($attempt);
            } catch (UnknownStripeOutcome|DefinitiveStripeFailure) {
                $request->session()->flash('checkout_notice', 'Consulta el estado del intento antes de volver a pagar.');
            }
        }
        if ($request->expectsJson()) {
            return response()->json(['data' => ['result_url' => route('orders.show', ['order' => $order->reference])]])
                ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
        }

        return $this->redirect('orders.show', ['order' => $order->reference]);
    }

    /** @return array{array<string,mixed>,QuoteData} */
    private function prepareReview(Request $request, ContactData $contact, bool $replace): array
    {
        $lines = $this->cart->lines();
        if ($lines === []) {
            throw ValidationException::withMessages(['cart' => 'El carrito está vacío. Agrega equipos antes de continuar.']);
        }
        try {
            $code = $request->session()->get('cart.coupon_code');
            if (is_string($code)) {
                try {
                    $quote = app(CouponReservationService::class)->preview($lines, $this->storefront->currentTier($request), $code, $contact->phoneCanonical);
                } catch (ValidationException|InvalidArgumentException|OverflowException) {
                    $request->session()->forget('cart.coupon_code');
                    $request->session()->flash('coupon_notice', 'El cupón ya no está disponible. Revisa y acepta el total sin descuento.');
                    $quote = $this->pricing->quote($lines, $this->storefront->currentTier($request));
                }
            } else {
                $quote = $this->pricing->quote($lines, $this->storefront->currentTier($request));
            }
        } catch (InvalidArgumentException|OverflowException) {
            throw ValidationException::withMessages(['cart' => 'No podemos calcular ese carrito. Revisa los artículos y cantidades.']);
        }
        $previous = $this->storedReview($request);
        if (isset($previous['order_reference'])) {
            $previous = null;
        }
        if ($replace || $previous === null || $previous['fingerprint'] !== $quote->fingerprint || $previous['user_id'] !== $request->user()?->id) {
            $previous = [
                'review_key' => (string) Str::uuid(), 'submission_key' => (string) Str::uuid(),
                'user_id' => $request->user()?->id, 'fingerprint' => $quote->fingerprint,
                'coupon_code' => $quote->couponSnapshot['code'] ?? null,
                'lines' => array_map(fn ($line): array => ['product_id' => $line->productId, 'quantity' => $line->quantity], $quote->lines),
                'contact' => ['name' => $contact->name, 'email' => $contact->email, 'phone' => $contact->phoneOriginal, 'phone_canonical' => $contact->phoneCanonical],
            ];
            $request->session()->put('checkout.review', $previous);
        }

        return [$previous, $quote];
    }

    /** @return array<string,mixed>|null */
    private function storedReview(Request $request): ?array
    {
        $review = $request->session()->get('checkout.review');
        if (! is_array($review) || ! is_array($review['contact'] ?? null) || ! is_array($review['lines'] ?? null)
            || ! is_string($review['review_key'] ?? null) || ! is_string($review['fingerprint'] ?? null)
            || ! is_string($review['submission_key'] ?? null) || ! array_key_exists('user_id', $review)) {
            return null;
        }

        return $review;
    }

    /** @param array<string,mixed> $review */
    private function contactData(array $review): ContactData
    {
        return new ContactData($review['contact']['name'], $review['contact']['email'], $review['contact']['phone'], $review['contact']['phone_canonical']);
    }

    private function activeActor(Request $request): ?User
    {
        if ($request->user() === null) {
            return null;
        }
        $actor = User::query()->find($request->user()->getAuthIdentifier());
        abort_unless($actor !== null && $actor->isActive() && $request->session()->get('auth.version') === $actor->auth_version, 403);

        return $actor;
    }

    private function limitCheckout(Request $request, string $type, int $maximum, int $seconds): void
    {
        $keys = ['checkout:'.$type.':session:'.hash('sha256', $request->session()->getId()), 'checkout:'.$type.':ip:'.hash('sha256', (string) $request->ip())];
        try {
            Cache::lock('checkout:limits:ip:'.hash('sha256', (string) $request->ip()), 5)->block(1, function () use ($keys, $maximum, $seconds): void {
                foreach ($keys as $key) {
                    abort_if(RateLimiter::tooManyAttempts($key, $maximum), 429);
                }
                foreach ($keys as $key) {
                    RateLimiter::hit($key, $seconds);
                }
            });
        } catch (LockTimeoutException) {
            abort(429);
        }
    }

    /** @param array<string,mixed> $parameters */
    private function redirect(string $route, array $parameters = []): RedirectResponse
    {
        return redirect()->route($route, $parameters)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
