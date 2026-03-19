<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #333; line-height: 1.6; margin: 0; padding: 0; background: #f5f5f5; }
        .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { background: #1976d2; color: #fff; padding: 20px 24px; }
        .header h1 { margin: 0; font-size: 18px; font-weight: 600; }
        .header .ticket-number { opacity: 0.85; font-size: 13px; margin-top: 4px; }
        .body { padding: 24px; }
        .message-box { background: #f0f7ff; border-left: 4px solid #1976d2; padding: 12px 16px; margin-bottom: 20px; border-radius: 0 4px 4px 0; }
        .field { margin-bottom: 12px; }
        .field-label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
        .field-value { font-size: 14px; }
        .properties { display: flex; flex-wrap: wrap; gap: 16px; margin: 16px 0; padding: 16px; background: #fafafa; border-radius: 6px; }
        .prop { flex: 1; min-width: 120px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-open { background: #fff3e0; color: #e65100; }
        .badge-in_progress { background: #e3f2fd; color: #1565c0; }
        .badge-pending { background: #f3e5f5; color: #7b1fa2; }
        .badge-resolved { background: #e8f5e9; color: #2e7d32; }
        .badge-closed { background: #f5f5f5; color: #616161; }
        .description { margin-top: 16px; padding: 16px; background: #fafafa; border-radius: 6px; }
        .footer { padding: 16px 24px; border-top: 1px solid #eee; font-size: 12px; color: #999; text-align: center; }
        .sender { font-weight: 600; color: #1976d2; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $ticket->title }}</h1>
            <div class="ticket-number">{{ $ticket->ticket_number }}</div>
        </div>

        <div class="body">
            @if($personalMessage)
                <div class="message-box">
                    <div class="field-label">Mensaje de <span class="sender">{{ $sender->name }}</span></div>
                    <div style="margin-top: 8px;">{{ $personalMessage }}</div>
                </div>
            @else
                <p><span class="sender">{{ $sender->name }}</span> ha compartido este ticket contigo.</p>
            @endif

            <div class="properties">
                <div class="prop">
                    <div class="field-label">Estado</div>
                    <span class="badge badge-{{ $ticket->status }}">
                        @php
                            $statusLabels = ['open' => 'Abierto', 'in_progress' => 'En Progreso', 'pending' => 'Pendiente', 'resolved' => 'Resuelto', 'closed' => 'Cerrado'];
                        @endphp
                        {{ $statusLabels[$ticket->status] ?? $ticket->status }}
                    </span>
                </div>
                <div class="prop">
                    <div class="field-label">Prioridad</div>
                    <div class="field-value">
                        @php
                            $priorityLabels = ['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'urgent' => 'Urgente'];
                        @endphp
                        {{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}
                    </div>
                </div>
                <div class="prop">
                    <div class="field-label">Tipo</div>
                    <div class="field-value">
                        @php
                            $typeLabels = ['incident' => 'Incidente', 'request' => 'Solicitud', 'problem' => 'Problema', 'change' => 'Cambio'];
                        @endphp
                        {{ $typeLabels[$ticket->type] ?? $ticket->type }}
                    </div>
                </div>
                <div class="prop">
                    <div class="field-label">Solicitante</div>
                    <div class="field-value">{{ $ticket->requester?->name ?? 'N/A' }}</div>
                </div>
            </div>

            @if($ticket->description)
                <div class="description">
                    <div class="field-label">Descripción</div>
                    <div style="margin-top: 8px;">{!! $ticket->description !!}</div>
                </div>
            @endif
        </div>

        <div class="footer">
            Enviado desde <strong>AutoService</strong> por {{ $sender->name }} ({{ $sender->email }})
        </div>
    </div>
</body>
</html>
