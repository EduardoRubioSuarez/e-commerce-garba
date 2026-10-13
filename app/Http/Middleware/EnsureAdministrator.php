<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministrator
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isActive() || ! $user->isAdministrator()) {
            abort(403);
        }
        $verified = $request->session()->get('admin.verified');
        if (! is_array($verified) || ($verified['user_id'] ?? null) !== $user->id || ($verified['auth_version'] ?? null) !== $user->auth_version) {
            if ($request->expectsJson()) {
                abort(403);
            }

            return redirect()->route('admin.second-factor')->header('Cache-Control', 'private, no-store');
        }

        return $next($request);
    }
}
