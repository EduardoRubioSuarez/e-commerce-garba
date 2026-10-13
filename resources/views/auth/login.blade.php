<x-layout title="Iniciar sesión">
    <section class="purchase-panel mx-auto max-w-md" aria-labelledby="titulo">
        <h1 id="titulo" class="text-3xl font-semibold tracking-tight">Iniciar sesión</h1>
        <p class="mt-3 text-base leading-6 text-slate-600">Accede con tu cuenta de Garba.</p>
        <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-6 rounded-lg border border-slate-200 bg-white p-6 sm:p-8">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" maxlength="255"
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                    class="mt-2 min-h-11 w-full rounded-md border border-slate-400 bg-white px-3 py-2 focus:border-blue-700 focus:outline-2 focus:outline-blue-700">
                @error('email') <p id="email-error" role="alert" class="mt-2 text-sm text-red-800">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-medium">Contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" maxlength="1024"
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                    class="mt-2 min-h-11 w-full rounded-md border border-slate-400 bg-white px-3 py-2 focus:border-blue-700 focus:outline-2 focus:outline-blue-700">
                @error('password') <p id="password-error" role="alert" class="mt-2 text-sm text-red-800">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="min-h-11 w-full whitespace-nowrap rounded-md bg-blue-800 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-blue-700">Entrar</button>
        </form>
        <a href="{{ route('password.request') }}" class="mt-5 inline-flex min-h-11 items-center text-blue-800 underline">Olvidé mi contraseña</a>
    </section>
</x-layout>
