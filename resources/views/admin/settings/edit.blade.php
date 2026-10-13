<x-layout title="Configuración del local">
    @php
        $inputs = [
            'Datos del local' => ['pickup_address' => ['Dirección', 'Donde el cliente recoge su pedido.'], 'pickup_hours' => ['Horario', 'Por ejemplo, "Atención las 24 horas" o "Lunes a viernes de 9:00 a 18:00".']],
            'Contacto' => ['contact_email' => ['Correo de contacto', 'Los clientes lo ven en la página de compra y recogida.'], 'contact_phone' => ['Teléfono de contacto', 'Opcional.']],
        ];
        $texts = ['terms' => ['Condiciones de compra', 'Se muestran en la página de compra y recogida.'], 'privacy' => ['Privacidad', 'Explica cómo se usan los datos de los clientes. Se muestra en la página de compra y recogida.'], 'returns' => ['Devoluciones', 'Ya no se muestra en la tienda, pero este campo sigue siendo obligatorio.'], 'retention_policy' => ['Política de retención', 'Documenta el acuerdo comercial. Los pedidos y la evidencia financiera siempre se conservan.']];
    @endphp
    <x-admin.page-header title="Configuración del local" :subtitle="'Versión '.$settings->version.'. Los cambios quedan auditados. Revisa los textos reales antes de abrir ventas.'" />
    <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-8 grid max-w-3xl gap-6">
        @csrf @method('PUT')
        @foreach($inputs as $groupTitle => $fields)
            <section class="panel grid gap-5 {{ count($fields) > 2 ? '' : 'sm:grid-cols-2' }}">
                <h2 class="panel-title sm:col-span-full">{{ $groupTitle }}</h2>
                @foreach($fields as $field => [$label, $help])
                    <div>
                        <label for="{{ $field }}" class="field-label">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'contact_email' ? 'email' : 'text' }}" value="{{ old($field, $settings->$field) }}" maxlength="{{ $field === 'contact_phone' ? 40 : 255 }}" @required($field !== 'contact_phone') aria-describedby="{{ $field }}-help @error($field) {{ $field }}-error @enderror" @error($field) aria-invalid="true" @enderror class="input">
                        <p id="{{ $field }}-help" class="field-help">{{ $help }}</p>
                        @error($field) <p id="{{ $field }}-error" role="alert" class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </section>
        @endforeach
        <section class="panel grid gap-6">
            <h2 class="panel-title sm:col-span-full">Textos legales</h2>
            @foreach($texts as $field => [$label, $help])
                <div>
                    <label for="{{ $field }}" class="field-label">{{ $label }}</label>
                    <textarea id="{{ $field }}" name="{{ $field }}" required maxlength="20000" rows="5" aria-describedby="{{ $field }}-help @error($field) {{ $field }}-error @enderror" @error($field) aria-invalid="true" @enderror class="input">{{ old($field, $settings->$field ?? config('store.information.'.$field, 'Pendiente de acordar; conservar evidencia financiera necesaria.')) }}</textarea>
                    <p id="{{ $field }}-help" class="field-help">{{ $help }}</p>
                    @error($field) <p id="{{ $field }}-error" role="alert" class="field-error">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </section>
        <div><button class="btn btn-primary">Guardar configuración</button></div>
    </form>
</x-layout>
