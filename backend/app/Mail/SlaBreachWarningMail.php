<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SlaBreachWarningMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $tenantName;

    public function __construct(
        public Ticket $ticket,
        public User $agent,
        public string $breachType,
        public int $minutesRemaining,
    ) {
        $this->ticket->loadMissing('requester', 'category');
        $this->tenantName = $ticket->tenant?->name ?? 'Chuyma';
    }

    public function envelope(): Envelope
    {
        $typeLabel = $this->breachType === 'response' ? 'respuesta' : 'resolución';
        $urgency = $this->minutesRemaining <= 0 ? 'SLA INCUMPLIDO' : 'Alerta SLA';

        return new Envelope(
            subject: "[{$urgency}] [{$this->ticket->ticket_number}] Límite de {$typeLabel}: {$this->ticket->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sla-breach-warning',
        );
    }
}
