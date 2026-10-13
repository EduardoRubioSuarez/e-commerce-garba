<?php

namespace App\Http\Controllers;

use App\Models\AdminLoginChallenge;
use App\Services\AdminSecondFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminSecondFactorController extends Controller
{
    public function create(Request $request): Response
    {
        abort_unless($request->user()->isAdministrator(), 403);

        return response()->view('auth.second-factor')->header('Cache-Control', 'private, no-store');
    }

    public function verify(Request $request, AdminSecondFactorService $service): RedirectResponse
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $data = $request->validate(['code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']]);
        $service->verify($request->user(), (string) $request->session()->get('admin.challenge'), $request->session()->getId(), $data['code']);
        $request->session()->regenerate();
        $request->session()->forget('admin.challenge');
        $request->session()->put('admin.verified', ['user_id' => $request->user()->id, 'auth_version' => $request->user()->auth_version]);
        $response = redirect()->route('admin.dashboard');
        if ($request->boolean('remember_device')) {
            $response->withCookie(cookie(AdminSecondFactorService::TRUSTED_DEVICE_COOKIE, $service->trustDevice($request->user()),
                AdminSecondFactorService::TRUSTED_DEVICE_DAYS * 1440, '/', null, $request->isSecure(), true, false, 'lax'));
        }

        return $response;
    }

    public function forgetDevices(Request $request, AdminSecondFactorService $service): RedirectResponse
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $service->forgetDevices($request->user());

        return back()->with('status', 'Se olvidaron todos los dispositivos recordados.')
            ->withCookie(cookie()->forget(AdminSecondFactorService::TRUSTED_DEVICE_COOKIE));
    }

    public function resend(Request $request, AdminSecondFactorService $service): RedirectResponse
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $old = AdminLoginChallenge::query()->find($request->session()->get('admin.challenge'));
        abort_if($old !== null && $old->created_at->gt(now()->subSeconds(60)), 429);
        $request->session()->put('admin.challenge', $service->begin($request->user(), $request->session()->getId()));

        return redirect()->route('admin.second-factor')->with('status', 'Nuevo código registrado. Revisa tu correo.');
    }
}
