<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function create(): Response
    {
        return response()->view('auth.password-reset', ['reset' => false])->header('Cache-Control', 'private, no-store');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        $email = mb_strtolower(trim($data['email']));
        $user = User::query()->where('email', $email)->first();
        if ($user?->isActive()) {
            Password::sendResetLink(['email' => $email]);
        }

        return back()->with('status', 'Si la cuenta está disponible, recibirás un enlace para recuperar tu contraseña.');
    }

    public function edit(Request $request, string $token): Response
    {
        return response()->view('auth.password-reset', ['reset' => true, 'token' => $token, 'email' => (string) $request->query('email', '')])
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed']]);
        $data['email'] = mb_strtolower(trim($data['email']));
        $status = Password::reset($data, function (User $user, #[\SensitiveParameter] string $password) use ($data): void {
            DB::transaction(function () use ($user, $password, $data): void {
                $current = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                if (! $current->isActive() || ! Password::tokenExists($current, $data['token'])) {
                    throw ValidationException::withMessages(['email' => 'El enlace no está disponible. Solicita uno nuevo.']);
                }
                $current->forceFill(['password' => $password, 'remember_token' => null, 'auth_version' => $current->auth_version + 1]);
                if (! $current->save()) {
                    throw new \RuntimeException('Password reset persistence failed.');
                }
                Password::deleteToken($current);
                if (Schema::hasTable(config('session.table', 'sessions'))) {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $current->id)->delete();
                }
                event(new PasswordReset($current));
            }, 3);
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'El enlace no está disponible. Solicita uno nuevo.']);
        }

        return redirect()->route('login')->with('success', 'Contraseña actualizada. Inicia sesión de nuevo.');
    }
}
