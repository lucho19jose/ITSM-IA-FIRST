@extends('emails.layout')

@section('title', '¿Cómo fue tu experiencia?')

@section('header-subtitle')
    Encuesta de satisfacción
@endsection

@section('preheader')
    Nos gustaría conocer tu opinión sobre el soporte que recibiste para el ticket {{ $ticketNumber }}.
@endsection

@section('content')
    <h2 style="margin: 0 0 8px; font-size: 20px; font-weight: 700; color: #1a1a2e;">
        Hola {{ $userName }},
    </h2>

    <p style="margin: 0 0 20px; color: #555; font-size: 15px; line-height: 1.6;">
        Tu ticket ha sido cerrado. Nos encantaría conocer tu opinión sobre la atención que recibiste.
    </p>

    <!-- Ticket Info Box -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f8f9fa; border-radius: 8px; margin-bottom: 24px;">
        <tr>
            <td style="padding: 16px 20px;">
                <div style="font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Ticket</div>
                <div style="font-size: 15px; color: #333; font-weight: 600;">{{ $ticketNumber }} &mdash; {{ $ticketTitle }}</div>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 16px; color: #555; font-size: 15px; text-align: center;">
        ¿Cómo calificarías tu experiencia?
    </p>

    <!-- Star Rating Buttons -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 28px;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        @for($i = 1; $i <= 5; $i++)
                        <td style="padding: 0 6px;">
                            <a href="{{ $surveyUrl }}?rating={{ $i }}" target="_blank" style="text-decoration: none; display: inline-block;">
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                    <tr>
                                        <td align="center" style="
                                            width: 56px;
                                            height: 56px;
                                            border-radius: 12px;
                                            background-color: {{ $i <= 2 ? '#fff3e0' : ($i === 3 ? '#e3f2fd' : '#e8f5e9') }};
                                            border: 2px solid {{ $i <= 2 ? '#ff9800' : ($i === 3 ? '#1976d2' : '#4caf50') }};
                                            text-align: center;
                                            vertical-align: middle;
                                        ">
                                            <span style="font-size: 28px; line-height: 1;">
                                                @if($i === 1) &#128542;
                                                @elseif($i === 2) &#128577;
                                                @elseif($i === 3) &#128528;
                                                @elseif($i === 4) &#128578;
                                                @elseif($i === 5) &#128525;
                                                @endif
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td align="center" style="padding-top: 6px;">
                                            <span style="font-size: 12px; color: #666; font-weight: 600;">{{ $i }}</span>
                                        </td>
                                    </tr>
                                </table>
                            </a>
                        </td>
                        @endfor
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding-top: 8px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="320">
                    <tr>
                        <td style="font-size: 11px; color: #999;" align="left">Muy insatisfecho</td>
                        <td style="font-size: 11px; color: #999;" align="right">Muy satisfecho</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 20px; color: #777; font-size: 13px; text-align: center; line-height: 1.5;">
        Haz clic en una de las opciones de arriba para calificar tu experiencia.<br>
        También podrás dejarnos un comentario opcional.
    </p>

    <!-- Fallback CTA -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td align="center">
                <a href="{{ $surveyUrl }}" class="btn" target="_blank" style="display: inline-block; padding: 12px 28px; background-color: #1976d2; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">
                    Responder encuesta
                </a>
            </td>
        </tr>
    </table>
@endsection
