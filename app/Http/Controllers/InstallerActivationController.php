<?php

namespace App\Http\Controllers;

use App\Models\InstallerInvitation;
use App\Services\InstallerInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InstallerActivationController extends Controller
{
    public function create(string $token): Response
    {
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/', $token) === 1, 404);
        $invitation = InstallerInvitation::query()->where('token_hash', hash('sha256', $token))->first();
        abort_unless($invitation !== null && $invitation->used_at === null && $invitation->revoked_at === null && $invitation->expires_at->isFuture(), 404);

        return response()->view('auth.activate', ['token' => $token])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request, InstallerInvitationService $service): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'string'], 'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed']]);
        $service->activate($data['token'], $data['password']);

        return redirect()->route('login')->with('success', 'Acceso activado. Ya puedes iniciar sesión.');
    }
}
