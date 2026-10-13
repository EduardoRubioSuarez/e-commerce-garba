<x-layout title="Verificar acceso">
    <h1 class="text-3xl font-semibold">Verifica tu acceso administrativo</h1>
    <p class="mt-3 max-w-xl text-slate-600">Ingresa el código de seis dígitos que enviamos a tu correo. Vence en cinco minutos y admite hasta cinco intentos.</p>
    <form method="POST" action="{{ route('admin.second-factor.verify') }}" class="mt-6 max-w-md space-y-5">
        @csrf
        <div><label for="code">Código de acceso</label><input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" minlength="6" required @error('code') aria-invalid="true" aria-describedby="code-error" @enderror class="mt-2 block min-h-11 w-full rounded border border-slate-300 p-3">
        @error('code')<p id="code-error" role="alert" class="mt-2 text-red-800">{{ $message }}</p>@enderror</div>
        <label class="flex items-center gap-3"><input type="checkbox" name="remember_device" value="1" class="h-5 w-5"> Recordar este dispositivo por 30 días</label>
        <button type="submit" class="min-h-11 rounded bg-blue-800 px-5 py-3 text-white">Verificar y continuar</button>
    </form>
    <form method="POST" action="{{ route('admin.second-factor.resend') }}" class="mt-5">
        @csrf
        <button type="submit" class="min-h-11 text-blue-800 underline">Solicitar otro código</button>
        <p class="mt-2 text-sm text-slate-600">Espera un minuto entre solicitudes. El nuevo código invalida el anterior.</p>
    </form>
</x-layout>
