<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Installment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InstallmentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Installment $installment,
        public string $clientName,
        public string $contractNo,
        public string $lotDescription,
        public string $value,
        public string $dueDate,
        public int $daysBefore,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Lembrete: parcela vence em {$this->daysBefore} dias — Contrato {$this->contractNo}",
            from: config('mail.from.address'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.installment-reminder');
    }
}
