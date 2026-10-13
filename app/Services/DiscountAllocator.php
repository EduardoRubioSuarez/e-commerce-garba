<?php

namespace App\Services;

use Brick\Math\BigInteger;
use InvalidArgumentException;

class DiscountAllocator
{
    /**
     * Largest remainder allocation; input order is the stable tie breaker.
     *
     * @param  list<int>  $bases
     * @return list<int>
     */
    public function allocate(array $bases, int $discountMinor): array
    {
        if (! array_is_list($bases) || $discountMinor < 0) {
            throw new InvalidArgumentException('Bases must be a list and discount must be nonnegative.');
        }
        $sum = 0;
        foreach ($bases as $base) {
            if (! is_int($base) || $base < 0 || $sum > PHP_INT_MAX - $base) {
                throw new InvalidArgumentException('Invalid or unrepresentable discount base.');
            }
            $sum += $base;
        }
        if ($discountMinor > $sum) {
            throw new InvalidArgumentException('Discount exceeds the base.');
        }
        $allocated = array_fill(0, count($bases), 0);
        if ($discountMinor === 0) {
            return $allocated;
        }
        $remainders = [];
        $remaining = $discountMinor;
        foreach ($bases as $index => $base) {
            [$quotient, $remainder] = BigInteger::of($discountMinor)->multipliedBy($base)->quotientAndRemainder($sum);
            $allocated[$index] = $quotient->toInt();
            $remaining -= $allocated[$index];
            $remainders[$index] = $remainder->toInt();
        }
        $order = array_keys($bases);
        usort($order, fn (int $a, int $b): int => ($remainders[$b] <=> $remainders[$a]) ?: ($a <=> $b));
        for ($index = 0; $index < $remaining; $index++) {
            $allocated[$order[$index]]++;
        }

        return $allocated;
    }
}
