<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordResetMail extends Mailable
{
    public function __construct(#[\SensitiveParameter] public string $token, public string $email) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recupera tu contraseña de Garba');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.password-reset');
    }
}
