<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InstallerInvitationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InstallerController extends Controller
{
    public function index(): View
    {
        return view('admin.installers.index', ['installers' => User::query()->where('role', UserRole::Installer)->withCount('orders')->orderByDesc('id')->paginate(20)]);
    }

    public function store(Request $request, InstallerInvitationService $invitations): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')]]);
        DB::transaction(function () use ($request, $data, $invitations): void {
            $installer = new User;
            $installer->forceFill($data + ['role' => UserRole::Installer, 'is_active' => false, 'activated_at' => null,
                'password' => bin2hex(random_bytes(32))]);
            if (! $installer->save()) {
                throw new \RuntimeException('Installer creation failed.');
            }
            $invitations->invite($installer, $request->user());
        }, 3);

        return redirect()->route('admin.installers.index')->with('success', 'Invitación registrada. Revisa su aceptación de envío antes de reenviar.');
    }

    public function resend(Request $request, User $installer, InstallerInvitationService $invitations): RedirectResponse
    {
        $invitations->invite($installer, $request->user());

        return redirect()->route('admin.installers.index')->with('success', 'Nueva invitación registrada; la anterior ya no es válida.');
    }

    public function suspend(Request $request, User $installer, InstallerInvitationService $invitations): RedirectResponse
    {
        $invitations->suspend($installer, $request->user());

        return redirect()->route('admin.installers.index')->with('success', 'Acceso suspendido. Los pagos pendientes conservan su historial y requieren confirmación de Stripe.');
    }

    public function reactivate(Request $request, User $installer, InstallerInvitationService $invitations): RedirectResponse
    {
        $invitations->reactivate($installer, $request->user());

        return redirect()->route('admin.installers.index')->with('success', $installer->fresh()->isActive()
            ? 'Acceso reactivado. El instalador ya puede entrar con su contraseña.'
            : 'Suspensión retirada. Como aún no crea su contraseña, reenvía su invitación.');
    }

    public function destroy(Request $request, User $installer, InstallerInvitationService $invitations): RedirectResponse
    {
        $invitations->delete($installer, $request->user());

        return redirect()->route('admin.installers.index')->with('success', 'Instalador eliminado.');
    }
}
