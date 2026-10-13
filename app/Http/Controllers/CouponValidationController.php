<?php

namespace App\Http\Controllers;

use App\Http\Requests\ValidateCouponRequest;
use App\Models\User;
use App\Services\CartService;
use App\Services\CouponReservationService;
use App\Services\PhoneNormalizer;
use App\Services\PricingService;
use App\Services\StorefrontService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OverflowException;

class CouponValidationController extends Controller
{
    public function __invoke(ValidateCouponRequest $request, CartService $cart, StorefrontService $storefront, PricingService $pricing, CouponReservationService $coupons): JsonResponse|RedirectResponse
    {
        if ($request->user() !== null) {
            $actor = User::find($request->user()->getAuthIdentifier());
            abort_unless($actor?->isActive() && $request->session()->get('auth.version') === $actor->auth_version, 403);
        }
        $data = $request->validated();
        $code = $data['coupon_code'] ?? null;
        $lines = $cart->lines();
        if ($lines === []) {
            throw ValidationException::withMessages(['cart' => 'Agrega artículos antes de validar un cupón.']);
        }
        try {
            $quote = $code === null || $code === ''
                ? $pricing->quote($lines, $storefront->currentTier($request))
                : $coupons->preview($lines, $storefront->currentTier($request), $code, app(PhoneNormalizer::class)->normalize($data['phone'], $data['country']));
        } catch (ValidationException $exception) {
            $request->session()->forget('cart.coupon_code');
            throw $exception;
        } catch (InvalidArgumentException|OverflowException) {
            $request->session()->forget('cart.coupon_code');
            throw ValidationException::withMessages(['coupon_code' => 'El cupón no aplica. Revisa y acepta el total sin cupón.']);
        }
        if ($quote->couponSnapshot === null) {
            $request->session()->forget('cart.coupon_code');
        } else {
            $request->session()->put('cart.coupon_code', $quote->couponSnapshot['code']);
        }
        if ($request->expectsJson()) {
            return response()->json(['data' => $quote->toArray()])
                ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
        }

        return redirect()->route('checkout.confirmation')->with('coupon_notice', 'Revisa el total actualizado antes de confirmar.');
    }
}
