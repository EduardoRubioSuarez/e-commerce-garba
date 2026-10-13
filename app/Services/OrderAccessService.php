<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class OrderAccessService
{
    public function __construct(private Session $session) {}

    public function issue(Order $order): string
    {
        $days = config('store.checkout.access_token_days', 7);
        if (! is_int($days) || $days < 1 || $days > 3650) {
            throw new RuntimeException('Order access token lifetime is invalid.');
        }
        $token = bin2hex(random_bytes(32));
        DB::transaction(function () use ($order, $days, $token): void {
            $current = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if ($current->token_hash !== null) {
                throw new LogicException('An order access token has already been issued.');
            }
            $current->forceFill(['token_hash' => hash('sha256', $token), 'token_expires_at' => now()->addDays($days)]);
            if (! $current->save()) {
                throw new RuntimeException('Order access token persistence was cancelled.');
            }
        }, 3);
        $order->refresh();

        return $token;
    }

    public function allows(Order $order, ?User $actor, ?string $token): bool
    {
        $current = Order::query()->find($order->getKey());
        if ($current === null) {
            return false;
        }
        if ($current->user_id !== null && ! User::query()->find($current->user_id)?->isActive()) {
            return false;
        }
        $owner = null;
        if ($actor !== null) {
            $owner = User::query()->find($actor->getAuthIdentifier());
            if ($owner === null || ! $owner->isActive() || $this->session->get('auth.version') !== $owner->auth_version) {
                return false;
            }
        }
        $key = 'orders.access.'.$current->reference;
        if ($token !== null) {
            if (! $this->tokenIsCurrent($current) || preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1
                || ! hash_equals($current->token_hash, hash('sha256', $token))) {
                return false;
            }
            $this->session->put($key, ['token_hash' => $current->token_hash]);

            return true;
        }
        if ($owner !== null && $current->user_id === $owner->id) {
            return true;
        }
        $grant = $this->session->get($key);
        if (! $this->tokenIsCurrent($current) || ! is_array($grant) || ! is_string($grant['token_hash'] ?? null)) {
            return false;
        }

        return hash_equals($current->token_hash, $grant['token_hash']);
    }

    private function tokenIsCurrent(Order $order): bool
    {
        return is_string($order->token_hash) && preg_match('/\A[a-f0-9]{64}\z/', $order->token_hash) === 1
            && $order->token_expires_at !== null && $order->token_expires_at->isFuture();
    }
}
