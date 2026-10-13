<?php

namespace App\Services;

use App\Models\AdminLoginChallenge;
use App\Models\TrustedAdminDevice;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AdminSecondFactorService
{
    public const TRUSTED_DEVICE_COOKIE = 'admin_trusted_device';

    public const TRUSTED_DEVICE_DAYS = 30;

    public function trustDevice(User $user): string
    {
        $token = Str::random(64);
        $device = new TrustedAdminDevice;
        $device->forceFill(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'auth_version' => $user->auth_version,
            'expires_at' => now()->addDays(self::TRUSTED_DEVICE_DAYS)]);
        $device->save();

        return $token;
    }

    public function isTrustedDevice(User $user, ?string $token): bool
    {
        if (! $user->isActive() || ! $user->isAdministrator() || ! is_string($token) || strlen($token) !== 64) {
            return false;
        }
        $device = TrustedAdminDevice::query()->where('token_hash', hash('sha256', $token))->first();
        if ($device === null || $device->user_id !== $user->id || $device->auth_version !== $user->auth_version || ! $device->expires_at->isFuture()) {
            return false;
        }
        $device->forceFill(['last_used_at' => now()])->save();

        return true;
    }

    public function forgetDevices(User $user): void
    {
        TrustedAdminDevice::query()->where('user_id', $user->id)->delete();
    }

    public function begin(User $user, string $sessionId): string
    {
        $key = 'admin-code:'.$user->id;
        Cache::lock($key.':lock', 10)->block(2, function () use ($key): void {
            abort_if(RateLimiter::tooManyAttempts($key, 3), 429);
            RateLimiter::hit($key, 300);
        });
        $id = (string) Str::uuid();

        return DB::transaction(function () use ($user, $sessionId, $id): string {
            $current = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->isActive() && $current->isAdministrator(), 403);
            $previous = AdminLoginChallenge::query()->where('user_id', $user->id)->orderByDesc('created_at')->lockForUpdate()->first();
            do {
                $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while ($previous !== null && Hash::check($code, $previous->code_hash));
            AdminLoginChallenge::query()->where('user_id', $user->id)->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $challenge = new AdminLoginChallenge;
            $challenge->forceFill(['id' => $id, 'user_id' => $current->id, 'session_hash' => hash('sha256', $sessionId),
                'auth_version' => $current->auth_version, 'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(5), 'attempts' => 0]);
            if (! $challenge->save()) {
                throw new RuntimeException('Administrative challenge persistence failed.');
            }
            app(NotificationDispatcher::class)->enqueueAccess($current, 'admin_login_code', 'admin-code:'.$id, ['challenge_id' => $id, 'code' => $code]);

            return $id;
        }, 3);
    }

    public function verify(User $user, string $challengeId, string $sessionId, #[\SensitiveParameter] string $code): void
    {
        $verified = DB::transaction(function () use ($user, $challengeId, $sessionId, $code): bool {
            $current = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $challenge = AdminLoginChallenge::query()->whereKey($challengeId)->lockForUpdate()->first();
            if (! $current->isActive() || ! $current->isAdministrator() || $challenge === null
                || $challenge->user_id !== $current->id || $challenge->auth_version !== $current->auth_version
                || ! hash_equals($challenge->session_hash, hash('sha256', $sessionId))
                || $challenge->used_at !== null || $challenge->revoked_at !== null
                || ! $challenge->expires_at->isFuture() || $challenge->attempts >= 5) {
                return false;
            }
            $challenge->attempts++;
            $matches = preg_match('/\A[0-9]{6}\z/', $code) === 1 && Hash::check($code, $challenge->code_hash);
            if ($matches) {
                $challenge->used_at = now();
            }
            if (! $challenge->save()) {
                throw new RuntimeException('Challenge verification persistence failed.');
            }

            return $matches;
        }, 3);
        if (! $verified) {
            throw ValidationException::withMessages(['code' => 'Código no válido o vencido. Solicita uno nuevo si es necesario.']);
        }
    }
}
