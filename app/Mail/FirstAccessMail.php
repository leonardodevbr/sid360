<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FirstAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $resetUrl,
        public bool $isFirstAccess = true
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->isFirstAccess
            ? 'Primeiro acesso ao ' . config('app.name')
            : 'Redefinição de senha - ' . config('app.name');

        return new Envelope(
            subject: $subject,
            from: config('mail.from.address'),
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.first-access'
        );
    }
}
