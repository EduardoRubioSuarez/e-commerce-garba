<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AdminSecondFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $request->session()->forget('admin');
        $request->session()->put('auth.version', $request->user()->auth_version);
        if ($request->user()->isAdministrator()) {
            $service = app(AdminSecondFactorService::class);
            if ($service->isTrustedDevice($request->user(), $request->cookie(AdminSecondFactorService::TRUSTED_DEVICE_COOKIE))) {
                $request->session()->put('admin.verified', ['user_id' => $request->user()->id, 'auth_version' => $request->user()->auth_version]);

                return redirect()->route('admin.dashboard');
            }
            $challenge = $service->begin($request->user(), $request->session()->getId());
            $request->session()->put('admin.challenge', $challenge);

            return redirect()->route('admin.second-factor');
        }

        return redirect()->route('home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
