@extends('emails.layout')

@section('preheader', "Tu ticket {$ticket->ticket_number} ha sido cerrado.")

@section('header-subtitle', "Ticket #{$ticket->ticket_number}")

@section('content')
    @php
        $priorityLabels = ['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'urgent' => 'Urgente'];
        $typeLabels = ['incident' => 'Incidente', 'request' => 'Solicitud', 'problem' => 'Problema', 'change' => 'Cambio'];
    @endphp

    <h2 style="margin: 0 0 8px; font-size: 18px; color: #333333;">{{ $ticket->title }}</h2>
    <p style="margin: 0 0 20px; font-size: 13px; color: #888888;">{{ $ticket->ticket_number }}</p>

    <p style="margin: 0 0 16px; color: #555555;">
        Hola <strong>{{ $ticket->requester?->name ?? 'Usuario' }}</strong>, tu ticket ha sido cerrado.
    </p>

    <div style="margin: 20px 0; padding: 16px; background-color: #e8f5e9; border-radius: 6px; text-align: center;">
        <span style="font-size: 24px;">&#10004;</span>
        <p style="margin: 8px 0 0; font-size: 16px; font-weight: 600; color: #2e7d32;">Ticket cerrado</p>
    </div>

    <!-- Properties -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="properties-table" style="margin: 20px 0; background-color: #f8f9fa; border-radius: 6px;">
        <tr>
            <td style="padding: 14px 16px; width: 50%;">
                <span style="font-size: 11px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">Tipo</span><br>
                <span style="font-size: 14px; color: #333333;">{{ $typeLabels[$ticket->type] ?? $ticket->type }}</span>
            </td>
            <td style="padding: 14px 16px; width: 50%;">
                <span style="font-size: 11px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">Prioridad</span><br>
                <span class="badge badge-{{ $ticket->priority }}" style="display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; margin-top: 4px;">{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 14px 16px; width: 50%;">
                <span style="font-size: 11px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">Resuelto por</span><br>
                <span style="font-size: 14px; color: #333333;">{{ $ticket->assignee?->name ?? 'N/A' }}</span>
            </td>
            <td style="padding: 14px 16px; width: 50%;">
                <span style="font-size: 11px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">Fecha de cierre</span><br>
                <span style="font-size: 14px; color: #333333;">{{ $ticket->closed_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</span>
            </td>
        </tr>
    </table>

    @if($ticket->resolution_notes)
        <div style="margin: 16px 0; padding: 16px; background-color: #fafafa; border-radius: 6px; border-left: 3px solid #2e7d32;">
            <span style="font-size: 11px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">Notas de resolución</span>
            <div style="margin-top: 8px; font-size: 14px; color: #444444;">{{ $ticket->resolution_notes }}</div>
        </div>
    @endif

    <p style="margin: 20px 0 8px; font-size: 13px; color: #999999;">
        Si consideras que el problema no fue resuelto, puedes solicitar la reapertura del ticket desde la plataforma.
    </p>
@endsection
