<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>Compra confirmada</title></head><body>
@php($money = fn (int $cents): string => \App\Support\Money::format($cents))
<h1>Compra confirmada</h1>
<p>Referencia: {{ $order->reference }}</p>
<ul>@foreach($order->lines as $line)<li>{{ $line->name }} — {{ $line->quantity }} — {{ $money($line->total_cents) }} {{ $order->currency }}</li>@endforeach</ul>
<p>Subtotal sin IVA: {{ $money($order->subtotal_cents) }} {{ $order->currency }}</p>
<p>Descuento: {{ $money($order->discount_cents) }} {{ $order->currency }}</p>
<p>IVA: {{ $money($order->tax_cents) }} {{ $order->currency }}</p>
<p>Total: {{ $money($order->total_cents) }} {{ $order->currency }}</p>
<p>Espera el segundo correo antes de acudir a recoger tu compra.</p>
<p>{{ $local->pickup_address }}<br>{{ $local->pickup_hours }}</p>
</body></html>