<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', 'Chuyma ITSM')</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        /* Reset */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { margin: 0; padding: 0; width: 100% !important; height: 100% !important; }
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; font-size: inherit !important; font-family: inherit !important; font-weight: inherit !important; line-height: inherit !important; }

        /* Base */
        body { background-color: #f4f6f8; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 14px; color: #333333; line-height: 1.6; }

        /* Utilities */
        .badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-open { background-color: #fff3e0; color: #e65100; }
        .badge-in_progress { background-color: #e3f2fd; color: #1565c0; }
        .badge-pending { background-color: #f3e5f5; color: #7b1fa2; }
        .badge-resolved { background-color: #e8f5e9; color: #2e7d32; }
        .badge-closed { background-color: #f5f5f5; color: #616161; }
        .badge-low { background-color: #e8f5e9; color: #2e7d32; }
        .badge-medium { background-color: #fff3e0; color: #e65100; }
        .badge-high { background-color: #fce4ec; color: #c62828; }
        .badge-urgent { background-color: #c62828; color: #ffffff; }

        .btn { display: inline-block; padding: 12px 28px; background-color: #1976d2; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; }
        .btn:hover { background-color: #1565c0; }

        .field-label { font-size: 11px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
        .field-value { font-size: 14px; color: #333333; }

        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .content-cell { padding: 20px 16px !important; }
            .properties-table td { display: block !important; width: 100% !important; padding-bottom: 12px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f8;">
    <!-- Preheader (hidden preview text) -->
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        @yield('preheader', '')
        &zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;
    </div>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f4f6f8;">
        <tr>
            <td align="center" style="padding: 24px 12px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="container" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.08);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #1976d2; padding: 20px 28px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="vertical-align: middle;">
                                        @if(isset($tenantName))
                                            <span style="color: #ffffff; font-size: 18px; font-weight: 700;">{{ $tenantName }}</span>
                                        @else
                                            <span style="color: #ffffff; font-size: 18px; font-weight: 700;">Chuyma</span>
                                        @endif
                                    </td>
                                </tr>
                                @hasSection('header-subtitle')
                                <tr>
                                    <td style="padding-top: 4px;">
                                        <span style="color: rgba(255,255,255,0.85); font-size: 13px;">@yield('header-subtitle')</span>
                                    </td>
                                </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td class="content-cell" style="padding: 28px;">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="border-top: 1px solid #eeeeee; padding: 16px 28px; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #999999;">
                                <strong>Chuyma</strong> &mdash; Plataforma ITSM
                            </p>
                            <p style="margin: 6px 0 0; font-size: 11px; color: #bbbbbb;">
                                Este correo fue enviado automáticamente. Por favor no responda directamente a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
