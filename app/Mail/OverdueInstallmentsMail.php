<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OverdueInstallmentsMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, array{number: string, due_date: string, value: string, days_overdue: int}> $overdueList
     */
    public function __construct(
        public string $clientName,
        public string $contractNo,
        public string $totalValue,
        public string $totalCorrected,
        public string $paymentDate,
        public array $overdueList,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Parcela(s) em atraso — Contrato {$this->contractNo}",
            from: config('mail.from.address'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.overdue-installments');
    }
}
