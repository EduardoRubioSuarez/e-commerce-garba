<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCartRequest;
use App\Models\Product;
use App\Models\ProductPhoto;
use App\Services\CartService;
use App\Services\CouponReservationService;
use App\Services\PricingService;
use App\Services\StorefrontService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OverflowException;

class CartController extends Controller
{
    public function show(Request $request, CartService $cart, PricingService $pricing, StorefrontService $storefront): Response|JsonResponse
    {
        $lines = $cart->lines();
        $tier = $storefront->currentTier($request);
        $products = Product::query()->whereIn('id', array_map(fn ($line): int => $line->productId, $lines))->get(['id', 'name', 'slug'])->keyBy('id');
        $items = array_map(fn ($line): array => [
            'product_id' => $line->productId,
            'name' => (string) $products->get($line->productId)->name,
            'slug' => (string) $products->get($line->productId)->slug,
            'quantity' => $line->quantity,
            'total_cents' => null,
        ], $lines);
        $data = ['lines' => $items, 'currency' => null, 'subtotal_cents' => null, 'tax_cents' => null, 'total_cents' => null];
        try {
            $code = $request->session()->get('cart.coupon_code');
            $phone = $request->session()->get('checkout.review.contact.phone_canonical');
            if (is_string($code) && is_string($phone)) {
                try {
                    $quote = app(CouponReservationService::class)->preview($lines, $tier, $code, $phone);
                } catch (ValidationException|InvalidArgumentException|OverflowException) {
                    $request->session()->forget('cart.coupon_code');
                    $request->session()->flash('cart_notice', 'El cupón ya no está disponible. Revisa y acepta el total sin descuento.');
                    $quote = $pricing->quote($lines, $tier);
                }
            } else {
                $quote = $pricing->quote($lines, $tier);
            }
            $quotedLines = collect($quote->lines)->keyBy('productId');
            foreach ($data['lines'] as &$item) {
                $item['total_cents'] = $quotedLines->get($item['product_id'])->totalCents;
            }
            unset($item);
            $data['currency'] = $quote->currency;
            $data['subtotal_cents'] = $quote->subtotalCents;
            $data['discount_cents'] = $quote->discountCents;
            $data['coupon'] = $quote->couponSnapshot;
            $data['tax_cents'] = $quote->taxCents;
            $data['total_cents'] = $quote->totalCents;
        } catch (InvalidArgumentException|OverflowException) {
            $data['error'] = 'No podemos calcular el total. Revisa los artículos o vuelve a intentarlo más tarde.';
        }
        if ($request->expectsJson()) {
            return response()->json(['data' => $data, 'notice' => $request->session()->get('cart_notice')])->header('Cache-Control', 'private, no-store');
        }

        $photos = ProductPhoto::query()->whereIn('product_id', array_keys($products->all()))->orderBy('sort_order')->orderBy('id')->get()->unique('product_id')
            ->mapWithKeys(fn (ProductPhoto $photo): array => [$photo->product_id => ['url' => Storage::disk('public')->url($photo->path), 'alt' => (string) $photo->alt]]);

        return response()->view('store.cart', compact('data', 'tier', 'photos'))->header('Cache-Control', 'private, no-store');
    }

    public function update(UpdateCartRequest $request, CartService $cart): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        if (($validated['action'] ?? 'put') === 'remove') {
            $cart->remove((int) $validated['product_id']);
            $notice = 'Equipo eliminado del carrito.';
        } else {
            $storedLines = $request->session()->get('cart.lines', []);
            $alreadyInCart = is_array($storedLines) && collect($storedLines)->contains(
                fn ($line): bool => is_array($line) && (int) ($line['product_id'] ?? 0) === (int) $validated['product_id'],
            );
            if (($validated['action'] ?? 'put') === 'add') {
                $cart->add((int) $validated['product_id'], (int) $validated['quantity']);
                $notice = 'Equipo agregado al carrito.';
            } else {
                $cart->put((int) $validated['product_id'], (int) $validated['quantity']);
                $notice = $alreadyInCart ? 'Cantidad actualizada.' : 'Equipo agregado al carrito.';
            }
        }
        if ($request->expectsJson()) {
            return response()->json(['message' => $notice, 'cart_count' => collect($cart->lines())->sum('quantity')])->header('Cache-Control', 'private, no-store');
        }
        $request->session()->flash('cart_notice', $notice);

        if (($validated['return_to'] ?? null) === 'catalog' && ($validated['action'] ?? 'put') !== 'remove') {
            return redirect()->back(fallback: route('store.catalog'))->header('Cache-Control', 'private, no-store');
        }

        if (($validated['return_to'] ?? null) === 'product' && ($validated['action'] ?? 'put') !== 'remove') {
            $product = Product::query()->findOrFail((int) $validated['product_id']);

            return redirect()->route('store.product', ['slug' => $product->slug])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('cart.show')->header('Cache-Control', 'private, no-store');
    }
}
