<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PurchaseConfirmed extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Compra confirmada — '.$this->order->reference);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.purchase-confirmed', with: ['order' => $this->order->load('lines'), 'local' => StoreSetting::current()]);
    }
}
