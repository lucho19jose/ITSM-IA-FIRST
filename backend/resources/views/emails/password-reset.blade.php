@extends('emails.layout')

@section('preheader', 'Solicitud de restablecimiento de contraseña')

@section('header-subtitle', 'Restablecer Contraseña')

@section('content')
    <h2 style="margin: 0 0 16px; font-size: 20px; color: #333333;">Hola {{ $user->name }},</h2>

    <p style="margin: 0 0 16px; color: #555555;">
        Recibimos una solicitud para restablecer la contraseña de tu cuenta en AutoService.
        Haz clic en el siguiente botón para crear una nueva contraseña:
    </p>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 24px auto;">
        <tr>
            <td style="border-radius: 6px; background-color: #e53935;">
                <a href="{{ $resetUrl }}" target="_blank"
                   style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px;">
                    Restablecer Contraseña
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 16px; color: #555555;">
        Este enlace expirará en <strong>60 minutos</strong>.
    </p>

    <p style="margin: 0 0 16px; color: #555555;">
        Si no solicitaste el restablecimiento de contraseña, puedes ignorar este correo.
        Tu cuenta permanecerá segura.
    </p>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 20px 0; background-color: #f8f9fa; border-radius: 6px; width: 100%;">
        <tr>
            <td style="padding: 12px 16px;">
                <p style="margin: 0; font-size: 12px; color: #888888;">
                    Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                    <span style="color: #e53935; word-break: break-all;">{{ $resetUrl }}</span>
                </p>
            </td>
        </tr>
    </table>
@endsection
