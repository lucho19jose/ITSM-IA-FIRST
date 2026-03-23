<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketClosedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $tenantName;

    public function __construct(
        public Ticket $ticket,
    ) {
        $this->ticket->loadMissing('requester', 'assignee', 'category');
        $this->tenantName = $ticket->tenant?->name ?? 'AutoService';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->ticket->ticket_number}] Ticket cerrado: {$this->ticket->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-closed',
        );
    }
}
