@extends('emails.layout')

@section('preheader', "Se te ha asignado el ticket {$ticket->ticket_number}: {$ticket->title}")

@section('header-subtitle', "Ticket #{$ticket->ticket_number}")

@section('content')
    @php
        $statusLabels = ['open' => 'Abierto', 'in_progress' => 'En Progreso', 'pending' => 'Pendiente', 'resolved' => 'Resuelto', 'closed' => 'Cerrado'];
        $priorityLabels = ['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'urgent' => 'Urgente'];
        $typeLabels = ['incident' => 'Incidente', 'request' => 'Solicitud', 'problem' => 'Problema', 'change' => 'Cambio'];
    @endphp

    <h2 style="margin: 0 0 8px; font-size: 18px; color: #333333;">{{ $ticket->title }}</h2>
    <p style="margin: 0 0 20px; font-size: 13px; color: #888888;">{{ $ticket->ticket_number }}</p>

    <p style="margin: 0 0 16px; color: #555555;">
        Hola <strong>{{ $agent->name }}</strong>, se te ha asignado un nuevo ticket.
        Por favor revísalo y toma las acciones necesarias.
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
                <span style="font-size: 11px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">Tipo</span><br>
                <span style="font-size: 14px; color: #333333;">{{ $typeLabels[$ticket->type] ?? $ticket->type }}</span>
            </td>
            <td style="padding: 14px 16px; width: 50%;">
                <span style="font-size: 11px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">Solicitante</span><br>
                <span style="font-size: 14px; color: #333333;">{{ $ticket->requester?->name ?? 'N/A' }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 14px 16px; width: 50%;" colspan="2">
                <span style="font-size: 11px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">Categoría</span><br>
                <span style="font-size: 14px; color: #333333;">{{ $ticket->category?->name ?? 'Sin categoría' }}</span>
            </td>
        </tr>
    </table>

    @if($ticket->description)
        <div style="margin: 16px 0; padding: 16px; background-color: #fafafa; border-radius: 6px; border-left: 3px solid #1976d2;">
            <span style="font-size: 11px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">Descripción</span>
            <div style="margin-top: 8px; font-size: 14px; color: #444444;">{!! \Illuminate\Support\Str::limit(strip_tags($ticket->description), 500) !!}</div>
        </div>
    @endif

    @if($ticket->resolution_due_at)
        <p style="margin: 16px 0 8px; color: #c62828; font-size: 13px;">
            <strong>Fecha límite de resolución:</strong> {{ $ticket->resolution_due_at->format('d/m/Y H:i') }}
        </p>
    @endif
@endsection
