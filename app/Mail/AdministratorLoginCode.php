<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AdministratorLoginCode extends Mailable
{
    public function __construct(#[\SensitiveParameter] public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Código de acceso administrativo de Garba');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.administrator-login-code');
    }
}
