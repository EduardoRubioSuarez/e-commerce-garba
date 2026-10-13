<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\CouponUse;
use App\Models\FinancialIncident;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\StripeEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['status' => ['sometimes', 'required', Rule::in(['all', ...array_column(OrderStatus::cases(), 'value')])]]);
        $status = $data['status'] ?? 'all';
        $orders = Order::query()->when($status !== 'all', fn (Builder $query): Builder => $query->where('status', $status))
            ->withSum('lines as items_count', 'quantity')->latest('id')->paginate(20)->withQueryString();

        return view('admin.orders.index', compact('orders', 'status'));
    }

    public function show(Order $order): View
    {
        $order->load(['lines', 'paymentAttempts']);
        $payments = Payment::query()->where('order_id', $order->id)->with('refunds')->orderBy('id')->get();
        $gross = (int) $payments->sum('amount_cents');
        $refunded = (int) Refund::query()->whereIn('payment_id', $payments->modelKeys())->where('status', 'succeeded')->sum('amount_cents');
        $incidents = FinancialIncident::query()->where('order_id', $order->id)->orderBy('id')->get();
        $notifications = OrderNotification::query()->where('order_id', $order->id)->orderBy('id')->get();
        $reservation = CouponUse::query()->where('order_id', $order->id)->first();
        $remoteIds = array_values(array_filter([
            ...$order->paymentAttempts->pluck('stripe_session_id')->all(),
            ...$order->paymentAttempts->pluck('stripe_payment_intent_id')->all(),
            ...$payments->pluck('stripe_charge_id')->all(),
            ...$payments->pluck('stripe_payment_intent_id')->all(),
            ...$payments->flatMap(fn (Payment $payment): Collection => $payment->refunds->pluck('stripe_refund_id'))->all(),
        ]));
        $events = StripeEvent::query()->where('status', 'pending')->where(function (Builder $query) use ($order, $remoteIds, $payments): void {
            $query->where('payload->data->object->metadata->order_reference', $order->reference)
                ->orWhereIn('payload->data->object->id', $remoteIds)
                ->orWhereIn('payload->data->object->charge', $payments->pluck('stripe_charge_id'))
                ->orWhereIn('payload->data->object->payment_intent', $payments->pluck('stripe_payment_intent_id'));
        })->orderBy('id')->paginate(20, ['id', 'stripe_event_id', 'type', 'status', 'received_at', 'last_checked_at'], 'events_page');

        return view('admin.orders.show', compact('order', 'payments', 'gross', 'refunded', 'incidents', 'notifications', 'reservation', 'events'));
    }
}
