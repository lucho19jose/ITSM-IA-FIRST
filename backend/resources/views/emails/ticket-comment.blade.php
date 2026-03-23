@extends('emails.layout')

@section('preheader', "Nuevo comentario en el ticket {$ticket->ticket_number}")

@section('header-subtitle', "Ticket #{$ticket->ticket_number}")

@section('content')
    <h2 style="margin: 0 0 8px; font-size: 18px; color: #333333;">{{ $ticket->title }}</h2>
    <p style="margin: 0 0 20px; font-size: 13px; color: #888888;">{{ $ticket->ticket_number }}</p>

    <p style="margin: 0 0 16px; color: #555555;">
        Hola <strong>{{ $recipient->name }}</strong>, se ha agregado un nuevo comentario a tu ticket.
    </p>

    <!-- Comment box -->
    <div style="margin: 20px 0; padding: 16px 20px; background-color: #f0f7ff; border-left: 4px solid #1976d2; border-radius: 0 6px 6px 0;">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td>
                    <span style="font-weight: 600; color: #1976d2;">{{ $comment->user?->name ?? 'Sistema' }}</span>
                    <span style="color: #999999; font-size: 12px; margin-left: 8px;">
                        {{ $comment->created_at->format('d/m/Y H:i') }}
                    </span>
                </td>
            </tr>
            <tr>
                <td style="padding-top: 10px;">
                    <div style="font-size: 14px; color: #444444; line-height: 1.6;">
                        {!! nl2br(e($comment->body)) !!}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    @php
        $statusLabels = ['open' => 'Abierto', 'in_progress' => 'En Progreso', 'pending' => 'Pendiente', 'resolved' => 'Resuelto', 'closed' => 'Cerrado'];
    @endphp

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 16px 0; font-size: 13px; color: #888888;">
        <tr>
            <td style="padding-right: 16px;">
                <strong>Estado:</strong> {{ $statusLabels[$ticket->status] ?? $ticket->status }}
            </td>
            <td>
                <strong>Asignado a:</strong> {{ $ticket->assignee?->name ?? 'Sin asignar' }}
            </td>
        </tr>
    </table>
@endsection
