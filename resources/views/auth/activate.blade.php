<x-layout title="Activar acceso">
    <h1 class="text-3xl font-semibold">Activa tu acceso de instalador</h1>
    <p class="mt-3 text-slate-600">Elige una contraseña de 12 a 72 caracteres. El enlace se utiliza una sola vez.</p>
    <form method="POST" action="{{ route('installer.activate.store') }}" class="mt-6 max-w-lg space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div><label for="password">Nueva contraseña</label><input id="password" type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password" class="mt-2 block min-h-11 w-full rounded border border-slate-300 p-3" @error('password') aria-invalid="true" aria-describedby="field-password-error" @enderror>
                @error('password')<p id="field-password-error" class="mt-2 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror</div>
        <div><label for="password_confirmation">Confirma la contraseña</label><input id="password_confirmation" type="password" name="password_confirmation" required minlength="12" maxlength="72" autocomplete="new-password" class="mt-2 block min-h-11 w-full rounded border border-slate-300 p-3" @error('password_confirmation') aria-invalid="true" aria-describedby="field-password_confirmation-error" @enderror>
                @error('password_confirmation')<p id="field-password_confirmation-error" class="mt-2 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror</div>
        <button class="min-h-11 rounded bg-blue-800 px-5 py-3 text-white" type="submit">Activar acceso</button>
    </form>
</x-layout>
