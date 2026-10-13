<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>Compra lista para recoger</title></head><body>
@php($money = fn (int $cents): string => \App\Support\Money::format($cents))
<h1>Tu compra está lista para recoger</h1>
<p>Referencia: {{ $order->reference }}</p>
<ul>@foreach($order->lines as $line)<li>{{ $line->name }} — {{ $line->quantity }} — {{ $money($line->total_cents) }} {{ $order->currency }}</li>@endforeach</ul>
<p>Total con IVA: {{ $money($order->total_cents) }} {{ $order->currency }}</p>
<p>Recoge tu compra en:<br>{{ $local->pickup_address }}<br>{{ $local->pickup_hours }}</p>
<p>Este mensaje comunica disponibilidad para recoger. Conserva la referencia de tu compra.</p>
</body></html>