<x-layout title="Recuperar contraseña">
    <h1 class="text-3xl font-semibold">{{ $reset ? 'Elige tu nueva contraseña' : 'Recupera tu contraseña' }}</h1>
    <form method="POST" action="{{ route($reset ? 'password.update' : 'password.email') }}" class="mt-6 max-w-lg space-y-5">
        @csrf
        @if($reset)<input type="hidden" name="token" value="{{ $token }}">@endif
        <div><label for="email">Correo electrónico</label><input id="email" name="email" type="email" required maxlength="255" autocomplete="username" value="{{ old('email', $email ?? '') }}" class="mt-2 block min-h-11 w-full rounded border border-slate-300 p-3" @error('email') aria-invalid="true" aria-describedby="field-email-error" @enderror>
                @error('email')<p id="field-email-error" class="mt-2 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror</div>
        @if($reset)
            <div><label for="password">Nueva contraseña</label><input id="password" name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password" class="mt-2 block min-h-11 w-full rounded border border-slate-300 p-3" @error('password') aria-invalid="true" aria-describedby="field-password-error" @enderror>
                @error('password')<p id="field-password-error" class="mt-2 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror</div>
            <div><label for="password_confirmation">Confirma la contraseña</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" maxlength="72" autocomplete="new-password" class="mt-2 block min-h-11 w-full rounded border border-slate-300 p-3" @error('password_confirmation') aria-invalid="true" aria-describedby="field-password_confirmation-error" @enderror>
                @error('password_confirmation')<p id="field-password_confirmation-error" class="mt-2 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror</div>
        @endif
        <button type="submit" class="min-h-11 rounded bg-blue-800 px-5 py-3 text-white">{{ $reset ? 'Actualizar contraseña' : 'Solicitar enlace' }}</button>
    </form>
    <a href="{{ route('login') }}" class="mt-6 inline-flex min-h-11 items-center text-blue-800 underline">Volver a iniciar sesión</a>
</x-layout>
