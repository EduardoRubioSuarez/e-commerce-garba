@props(['title' => 'Garba'])
@php($cartQuantity = collect(app(\App\Services\CartService::class)->lines())->sum('quantity'))
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Garba</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="storefront min-h-dvh font-sans antialiased">
    <a href="#contenido" class="skip-link">Ir al contenido</a>
    <div class="store-strip"><div class="store-container">Aire acondicionado · Compra en línea y recoge en local</div></div>
    <header class="store-header" data-nav>
        <div class="nav-float">
            <div class="store-container nav-inner">
                <a href="{{ route('home') }}" class="store-brand" aria-label="Garba, inicio"><span class="brand-symbol" aria-hidden="true">G</span><span>Garba<span class="brand-caption">CLIMATIZACIÓN</span></span></a>
                <nav aria-label="Navegación principal" class="store-nav" id="menu-principal" data-nav-menu>
                    @auth
                        @if(auth()->user()->isAdministrator())<a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.*')) aria-current="page" @endif>Administración</a>@endif
                    @endauth
                    <a href="{{ route('store.catalog') }}" @if(request()->routeIs('home', 'store.catalog', 'store.product')) aria-current="page" @endif>Catálogo</a>
                    <a href="{{ route('store.information') }}" @if(request()->routeIs('store.information')) aria-current="page" @endif>Recogida</a>
                    @auth
                        <form method="POST" action="{{ route('logout') }}" data-submit-feedback>@csrf<button type="submit">Cerrar sesión</button></form>
                    @endauth
                    @guest
                        <a href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>Iniciar sesión</a>
                    @endguest
                </nav>
                <a href="{{ route('cart.show') }}" class="nav-cart" aria-label="Carrito, {{ $cartQuantity }} {{ $cartQuantity === 1 ? 'producto' : 'productos' }}" @if(request()->routeIs('cart.*')) aria-current="page" @endif><x-icon.cart class="nav-icon" /><span class="cart-count" aria-hidden="true">{{ $cartQuantity }}</span></a>
                <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="menu-principal" aria-label="Abrir menú" data-nav-toggle><x-icon.menu class="nav-icon" data-icon-open /><x-icon.close class="nav-icon" data-icon-close hidden /></button>
            </div>
        </div>
    </header>
    <div class="notice-stack" aria-label="Notificaciones">
        @foreach(['cart_notice' => 'Tu carrito', 'coupon_notice' => 'Cupón', 'checkout_notice' => 'Tu pedido', 'success' => 'Confirmación', 'status' => 'Aviso'] as $key => $label)
            @if(session($key))
                <div role="status" aria-live="polite" class="store-notice" data-notice><span class="notice-label">{{ $label }}</span><p>{{ session($key) }}</p><button type="button" class="notice-close" data-notice-close aria-label="Cerrar notificación"><span aria-hidden="true">&times;</span></button><span class="notice-progress" aria-hidden="true"></span></div>
            @endif
        @endforeach
        @if(isset($errors) && $errors->any())
            <x-notice type="error" label="Revisa los datos" :focus="true"><ul class="notice-list">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-notice>
        @endif
        @stack('notices')
    </div>
    <main id="contenido" class="store-container store-main">
        @unless(request()->routeIs('home'))
            @php($previous = strtok(url()->previous(), '?#'))
            @php($previous = $previous !== strtok(url()->current(), '?#') ? $previous : route('store.catalog'))
            <a href="{{ $previous }}" class="back-link" data-back-link><span aria-hidden="true">&larr;</span> Volver</a>
        @endunless
        {{ $slot }}
    </main>
    <footer class="store-footer"><div class="store-container footer-inner"><div><a href="{{ route('home') }}" class="footer-brand">Garba</a><p>Equipos de aire acondicionado.</p></div><div><a href="{{ route('store.information') }}">Compra y recogida</a><a href="{{ route('store.information') }}#privacy">Privacidad</a></div><p>Precios con IVA · Recogida en local</p></div></footer>
</body>
</html>
