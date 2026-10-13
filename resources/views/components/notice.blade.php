@props(['label' => 'Aviso', 'type' => 'info', 'focus' => false])
@push('notices')
    <div role="{{ $type === 'error' ? 'alert' : 'status' }}" @if($type !== 'error') aria-live="polite" @endif class="store-notice{{ $type === 'error' ? ' notice-error' : '' }}" data-notice @if($focus) tabindex="-1" data-error-summary @endif><span class="notice-label">{{ $label }}</span>{{ $slot }}<button type="button" class="notice-close" data-notice-close aria-label="Cerrar notificación"><span aria-hidden="true">&times;</span></button><span class="notice-progress" aria-hidden="true"></span></div>
@endpush
