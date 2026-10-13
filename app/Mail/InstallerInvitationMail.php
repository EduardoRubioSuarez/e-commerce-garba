<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InstallerInvitationMail extends Mailable
{
    public function __construct(public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Activa tu acceso de instalador a Garba');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.installer-invitation');
    }
}
