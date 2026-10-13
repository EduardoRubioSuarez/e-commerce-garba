<?php

namespace App\Services;

use App\Data\CartLineData;
use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OverflowException;

class CartService
{
    public function __construct(
        private Session $session,
        private PricingService $pricing,
        private StorefrontService $storefront,
        private Request $request,
    ) {}

    /** @return list<CartLineData> */
    public function lines(): array
    {
        return $this->normalizedLines(persist: true);
    }

    /** @return list<CartLineData> */
    private function normalizedLines(bool $persist): array
    {
        $stored = $this->session->get('cart.lines', []);
        $candidates = [];
        if (is_array($stored) && array_is_list($stored)) {
            foreach ($stored as $item) {
                if (! is_array($item) || ! is_int($item['product_id'] ?? null) || ! is_int($item['quantity'] ?? null)
                    || $item['product_id'] < 1 || $item['quantity'] < 1 || isset($candidates[$item['product_id']])) {
                    continue;
                }
                $candidates[$item['product_id']] = new CartLineData($item['product_id'], $item['quantity']);
            }
        }
        $visibleIds = Product::query()->whereIn('id', array_keys($candidates))->where('is_active', true)->where('is_published', true)->pluck('id')->all();
        $lines = array_values(array_intersect_key($candidates, array_flip($visibleIds)));
        $canonical = array_map(fn (CartLineData $line): array => $line->toArray(), $lines);
        if ($persist && $stored !== $canonical) {
            $this->session->put('cart.lines', $canonical);
            $this->session->flash('cart_notice', 'Actualizamos tu carrito: revisa los artículos y cantidades.');
        }

        return $lines;
    }

    public function put(int $productId, int $quantity): void
    {
        if ($productId < 1 || ! Product::query()->whereKey($productId)->where('is_active', true)->where('is_published', true)->exists()) {
            throw ValidationException::withMessages(['product_id' => 'Este producto ya no está disponible. Elige otro equipo del catálogo.']);
        }
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser un entero positivo.']);
        }
        $candidate = array_values(array_filter($this->normalizedLines(persist: false), fn (CartLineData $line): bool => $line->productId !== $productId));
        $candidate[] = new CartLineData($productId, $quantity);
        try {
            $this->pricing->quote($candidate, $this->storefront->currentTier($this->request));
        } catch (InvalidArgumentException|OverflowException) {
            throw ValidationException::withMessages(['quantity' => 'No podemos calcular ese carrito. Revisa la cantidad o los artículos e inténtalo de nuevo.']);
        }
        $this->session->put('cart.lines', array_map(fn (CartLineData $line): array => $line->toArray(), $candidate));
    }

    /** Suma unidades a la cantidad actual (o crea la línea) sin reemplazarla. */
    public function add(int $productId, int $quantity): void
    {
        $current = 0;
        foreach ($this->normalizedLines(persist: false) as $line) {
            if ($line->productId === $productId) {
                $current = $line->quantity;
            }
        }
        if ($current > PHP_INT_MAX - $quantity) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser un entero positivo dentro del límite permitido.']);
        }

        $this->put($productId, $current + $quantity);
    }

    public function remove(int $productId): void
    {
        $lines = array_values(array_filter($this->lines(), fn (CartLineData $line): bool => $line->productId !== $productId));
        $this->session->put('cart.lines', array_map(fn (CartLineData $line): array => $line->toArray(), $lines));
    }

    public function clear(): void
    {
        $this->session->put('cart.lines', []);
        $this->session->forget('cart.coupon_code');
    }
}
