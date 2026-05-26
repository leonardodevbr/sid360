<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeSaleMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Sale $sale,
        public string $buyerName,
        public string $contractNo,
        public string $lotDescription,
        public string $totalValue,
        public string $firstDueDate,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bem-vindo(a) à Sid360 Imóveis! Sua compra foi registrada',
            from: config('mail.from.address'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.welcome-sale');
    }
}
