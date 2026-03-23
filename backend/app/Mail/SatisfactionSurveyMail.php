<?php

namespace App\Mail;

use App\Models\SatisfactionSurvey;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SatisfactionSurveyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $surveyUrl;
    public string $ticketNumber;
    public string $ticketTitle;
    public string $userName;

    public function __construct(
        public SatisfactionSurvey $survey,
        public Ticket $ticket,
    ) {
        $this->surveyUrl = rtrim(config('app.frontend_url', config('app.url')), '/') . '/survey/' . $survey->token;
        $this->ticketNumber = $ticket->ticket_number;
        $this->ticketTitle = $ticket->title;
        $this->userName = $ticket->requester?->name ?? 'Usuario';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "¿Cómo fue tu experiencia? - Ticket #{$this->ticketNumber}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.satisfaction-survey',
        );
    }
}
