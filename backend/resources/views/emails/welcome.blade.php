@extends('emails.layout')

@section('preheader', "Bienvenido a {$tenantName}. Tu cuenta ha sido creada exitosamente.")

@section('header-subtitle', 'Bienvenido')

@section('content')
    <h2 style="margin: 0 0 16px; font-size: 20px; color: #333333;">¡Hola {{ $user->name }}!</h2>

    <p style="margin: 0 0 16px; color: #555555;">
        Tu cuenta en <strong>{{ $tenantName }}</strong> ha sido creada exitosamente. Ya puedes acceder a la plataforma
        de gestión de servicios de TI.
    </p>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 20px 0; background-color: #f8f9fa; border-radius: 6px; width: 100%;">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="margin: 0 0 8px;">
                    <span class="field-label" style="font-size: 11px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">Correo electrónico</span><br>
                    <span style="font-size: 14px; color: #333333;">{{ $user->email }}</span>
                </p>
                <p style="margin: 0;">
                    <span class="field-label" style="font-size: 11px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">Rol</span><br>
                    <span style="font-size: 14px; color: #333333;">
                        @php
                            $roleLabels = ['admin' => 'Administrador', 'agent' => 'Agente', 'end_user' => 'Usuario Final'];
                        @endphp
                        {{ $roleLabels[$user->role] ?? $user->role }}
                    </span>
                </p>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 12px; color: #555555;">
        Con Chuymadesk puedes:
    </p>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 0 20px;">
        <tr>
            <td style="padding: 4px 0; color: #555555;">&#10003;&nbsp; Crear y dar seguimiento a tus tickets de soporte</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; color: #555555;">&#10003;&nbsp; Consultar la base de conocimientos</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; color: #555555;">&#10003;&nbsp; Solicitar servicios del catálogo</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; color: #555555;">&#10003;&nbsp; Recibir notificaciones en tiempo real</td>
        </tr>
    </table>

    <p style="margin: 0 0 8px; font-size: 13px; color: #999999;">
        Si tienes alguna consulta, no dudes en contactar al equipo de soporte de tu organización.
    </p>
@endsection
