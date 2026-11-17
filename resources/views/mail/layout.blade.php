<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $subject ?? config('app.name') }}</title>
    {{--
        Email clients ignore external CSS/JS, so everything is table-based with inline styles
        (inline values are the light-mode colours). The <style> block only adds dark-mode
        overrides for clients that support them. Mirrors the old site's MJML emails: blue
        header with logo and title, white letter body, blue footer, 600px wide.
    --}}
    <style>
        :root { color-scheme: light dark; supported-color-schemes: light dark; }
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; }
        img { border: 0; outline: none; text-decoration: none; }
        .content p { margin: 0 0 16px; }

        @media (prefers-color-scheme: dark) {
            .bg-page { background-color: #121212 !important; }
            .bg-body { background-color: #1E1E1E !important; }
            .text-body { color: #E5E5E5 !important; }
            .link { color: #4DB8F0 !important; }
        }

        /* Outlook.com / Outlook apps dark mode */
        [data-ogsc] .text-body { color: #E5E5E5 !important; }
        [data-ogsc] .link { color: #4DB8F0 !important; }
        [data-ogsb] .bg-page { background-color: #121212 !important; }
        [data-ogsb] .bg-body { background-color: #1E1E1E !important; }
    </style>
</head>
<body class="bg-page" style="margin: 0; padding: 0; background-color: #FFFFFF;">
    <table role="presentation" class="bg-page" width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#FFFFFF" style="width: 100%; background-color: #FFFFFF;">
        <tr>
            <td align="center" style="padding: 0;">
                <!--[if mso]><table role="presentation" width="600" align="center" border="0" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
                <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="width: 100%; max-width: 600px; margin: 0 auto; font-family: Poppins, -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif;">
                    <tr>
                        <td align="center" bgcolor="#0080C2" style="background-color: #0080C2; padding: 20px 25px;">
                            {{-- Embedded images only render when the mail is actually sent (e.g. via the mail sandbox), not in browser previews of the Mailable. --}}
                            {{-- Nested centred table: the only centring that holds in Outlook (Word engine) and in clients that strip inline-block / auto margins.
                                 The right padding optically centres the logo: the sun sits left of the wordmark, so a true-centred logo looks shifted right.
                                 The table stays centred, so the logo moves left by half the padding (24px -> 12px). --}}
                            <table role="presentation" align="center" border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                                <tr>
                                    <td align="center" style="padding: 0 24px 0 0; line-height: 0; font-size: 0;">
                                        <a href="{{ url('/') }}" target="_blank" style="display: block;">
                                            {{-- High-res source (1742x393) scaled down so it stays sharp on retina screens. --}}
                                            <img src="{{ $message->embed(public_path('images/zjx_wide_large.png')) }}" width="220" height="50" align="middle" alt="Virtual Jacksonville ARTCC" style="display: block; width: 220px; max-width: 100%; height: auto; margin: 0 auto;">
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            @hasSection('heading')
                                <div style="padding-top: 12px; color: #EDEDF5; font-size: 16px; font-weight: bold; line-height: 1.4; text-align: center;">@yield('heading')</div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="bg-body text-body content" bgcolor="#FFFFFF" style="background-color: #FFFFFF; padding: 25px; color: #000000; font-size: 16px; line-height: 1.5; text-align: left;">
                            @yield('content')

                            <p style="margin: 24px 0 0;">
                                Regards,<br>
                                The vZJX Staff<br>
                                <a href="mailto:zjx-staff@vatusa.net" class="link" style="color: #0080C2;">zjx-staff@vatusa.net</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" bgcolor="#0080C2" style="background-color: #0080C2; padding: 20px 25px; color: #FFFFFF; font-size: 12px; line-height: 1.5; text-align: center;">
                            <p style="margin: 0 0 12px; font-size: 14px;">&copy; {{ date('Y') }} Virtual Jacksonville ARTCC. All rights reserved.</p>
                            <p style="margin: 0 0 12px;">Do not reply to this email unless it asks you to do so; this inbox might be unmonitored.</p>
                            <p style="margin: 0;">vZJX has no affiliation with the real Jacksonville ARTCC, the Federal Aviation Administration, or any governing aviation authority. vZJX is a subdivision of VATUSA and VATNA on the VATSIM network.</p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]></td></tr></table><![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
