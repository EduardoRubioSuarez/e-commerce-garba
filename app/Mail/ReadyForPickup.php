<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReadyForPickup extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Compra lista para recoger — '.$this->order->reference);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ready-for-pickup', with: ['order' => $this->order->load('lines'), 'local' => StoreSetting::current()]);
    }
}
