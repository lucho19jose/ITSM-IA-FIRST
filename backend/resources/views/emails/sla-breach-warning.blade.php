@extends('emails.layout')

@php
    $typeLabel = $breachType === 'response' ? 'respuesta' : 'resolución';
    $isBreached = $minutesRemaining <= 0;
    $statusLabels = ['open' => 'Abierto', 'in_progress' => 'En Progreso', 'pending' => 'Pendiente', 'resolved' => 'Resuelto', 'closed' => 'Cerrado'];
    $priorityLabels = ['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'urgent' => 'Urgente'];
@endphp

@section('preheader')
    @if($isBreached)
        SLA de {{ $typeLabel }} incumplido para el ticket {{ $ticket->ticket_number }}
    @else
        Alerta: el SLA de {{ $typeLabel }} del ticket {{ $ticket->ticket_number }} vence en {{ $minutesRemaining }} minutos
    @endif
@endsection

@section('header-subtitle', "Ticket #{$ticket->ticket_number}")

@section('content')
    <h2 style="margin: 0 0 8px; font-size: 18px; color: #333333;">{{ $ticket->title }}</h2>
    <p style="margin: 0 0 20px; font-size: 13px; color: #888888;">{{ $ticket->ticket_number }}</p>

    @if($isBreached)
        <div style="margin: 0 0 20px; padding: 16px; background-color: #fce4ec; border-radius: 6px; text-align: center;">
            <span style="font-size: 24px;">&#9888;</span>
            <p style="margin: 8px 0 0; font-size: 16px; font-weight: 700; color: #c62828;">SLA de {{ $typeLabel }} incumplido</p>
            <p style="margin: 4px 0 0; font-size: 13px; color: #c62828;">
                El tiempo de {{ $typeLabel }} ha sido superado por {{ abs($minutesRemaining) }} minutos.
            </p>
        </div>
    @else
        <div style="margin: 0 0 20px; padding: 16px; background-color: #fff3e0; border-radius: 6px; text-align: center;">
            <span style="font-size: 24px;">&#9200;</span>
            <p style="margin: 8px 0 0; font-size: 16px; font-weight: 700; color: #e65100;">Alerta de SLA de {{ $typeLabel }}</p>
            <p style="margin: 4px 0 0; font-size: 13px; color: #e65100;">
                Quedan <strong>{{ $minutesRemaining }} minutos</strong> para cumplir con el SLA de {{ $typeLabel }}.
            </p>
        </div>
    @endif

    <p style="margin: 0 0 16px; color: #555555;">
        Hola <strong>{{ $agent->name }}</strong>, este ticket requiere atención inmediata.
    </p>

    <!-- Properties -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="properties-table" style="margin: 20px 0; background-color: #f8f9fa; border-radius: 6px;">
        <tr>
            <td style="padding: 14px 16px; width: 50%;">
                <span style="font-size: 11px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">Estado</span><br>
                <span class="badge badge-{{ $ticket->status }}" style="display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; margin-top: 4px;">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span>
            </td>
            <td style="padding: 14px 16px; width: 50%;">
                <span style="font-size: 11px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">Prioridad</span><br>
                <span class="badge badge-{{ $ticket->priority }}" style="display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; margin-top: 4px;">{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 14px 16px; width: 50%;">
                <span style="font-size: 11px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">Solicitante</span><br>
                <span style="font-size: 14px; color: #333333;">{{ $ticket->requester?->name ?? 'N/A' }}</span>
            </td>
            <td style="padding: 14px 16px; width: 50%;">
                <span style="font-size: 11px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">Fecha límite</span><br>
                <span style="font-size: 14px; color: #c62828; font-weight: 600;">
                    @if($breachType === 'response')
                        {{ $ticket->response_due_at?->format('d/m/Y H:i') ?? 'N/A' }}
                    @else
                        {{ $ticket->resolution_due_at?->format('d/m/Y H:i') ?? 'N/A' }}
                    @endif
                </span>
            </td>
        </tr>
    </table>

    <p style="margin: 16px 0 8px; font-size: 13px; color: #999999;">
        Por favor, toma acción sobre este ticket lo antes posible para cumplir con los acuerdos de nivel de servicio.
    </p>
@endsection
