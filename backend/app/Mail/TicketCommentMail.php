<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketCommentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $tenantName;
    public Ticket $ticket;

    public function __construct(
        public TicketComment $comment,
        public User $recipient,
    ) {
        $this->comment->loadMissing('user', 'ticket');
        $this->ticket = $this->comment->ticket;
        $this->ticket->loadMissing('requester');
        $this->tenantName = $this->ticket->tenant?->name ?? 'Chuyma';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->ticket->ticket_number}] Nuevo comentario: {$this->ticket->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-comment',
        );
    }
}
