<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currentUser = $request->user();

        if (! $currentUser) {
            abort(401);
        }

        $freshUser = User::find($currentUser->getAuthIdentifier());

        if (! $freshUser || ! $freshUser->isActive() || $request->session()->get('auth.version') !== $freshUser->auth_version) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            $request->setUserResolver(fn () => null);

            abort(403);
        }

        Auth::guard('web')->setUser($freshUser);
        $request->setUserResolver(fn () => $freshUser);

        return $next($request);
    }
}
