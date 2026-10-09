@php
    $brandName = config('app.name', 'Legal Bruz Ltd.');
    $brandEmail = config('mail.from.address', 'info@legalbruz.com');
    $brandUrl = config('app.url');
    $logoUrl = asset('legal-bruz-ltd-logo.png');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('email_title', $brandName)</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #eef4f7;
            color: #243044;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
        }

        .email-shell {
            max-width: 680px;
            margin: 0 auto;
            background: #ffffff;
        }

        .email-topbar {
            height: 8px;
            background: #0f9f90;
        }

        .email-header {
            padding: 28px 32px 18px;
            border-bottom: 1px solid #dbe7ee;
            background: #f8fcfd !important;
            color: #102a4c !important;
            text-align: center;
        }

        .brand-logo img {
            display: block;
            width: 96px;
            max-width: 100%;
            height: auto;
            margin: 0 auto 12px;
        }

        .brand-name {
            margin: 0 0 8px;
            color: #102a4c;
            font-size: 22px;
            font-weight: 800;
        }

        .preheader {
            margin: 0;
            color: #5f6e84;
            font-size: 14px;
            font-weight: 700;
        }

        .email-body {
            padding: 30px 32px 34px;
        }

        .status-line {
            display: inline-block;
            margin-bottom: 16px;
            padding: 7px 12px;
            border-radius: 999px;
            background: #e7f8f5;
            color: #0b8176;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .email-heading {
            margin: 0 0 18px;
            color: #102a4c;
            font-size: 28px;
            line-height: 1.25;
            font-weight: 800;
        }

        .email-heading strong {
            color: #0f9f90;
        }

        .email-body p {
            margin: 0 0 16px;
            font-size: 16px;
        }

        .summary-card {
            margin: 24px 0;
            border: 1px solid #d9e5ee;
            border-radius: 10px;
            overflow: hidden;
            background: #ffffff;
        }

        .summary-title {
            padding: 13px 16px;
            background: #f3f9fb;
            color: #102a4c;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table th,
        .summary-table td {
            padding: 12px 16px;
            border-top: 1px solid #edf3f7;
            text-align: left;
            vertical-align: top;
            font-size: 15px;
        }

        .summary-table th {
            width: 38%;
            color: #56677f;
            font-weight: 800;
        }

        .summary-table td {
            color: #243044;
            font-weight: 600;
        }

        .status-pill {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            background: #def7e8;
            color: #087443;
            font-size: 13px;
            font-weight: 800;
        }

        .status-pill.warning {
            background: #fff1d6;
            color: #935100;
        }

        .status-pill.danger {
            background: #ffe1e5;
            color: #a11428;
        }

        .admin-note {
            margin: 22px 0;
            padding: 16px;
            border: 1px solid #f5c56b;
            border-left: 5px solid #f0a81f;
            border-radius: 10px;
            background: #fff8e8;
            color: #6f4307;
        }

        .admin-note strong {
            display: block;
            margin-bottom: 6px;
            color: #8a5307;
        }

        .info-box {
            margin: 22px 0;
            padding: 15px 16px;
            border: 1px solid #bde7e1;
            border-left: 5px solid #0f9f90;
            border-radius: 10px;
            background: #effbf9;
            color: #164e49;
        }

        .cta-wrap {
            margin: 28px 0 4px;
        }

        .primary-button {
            display: inline-block;
            padding: 13px 20px;
            border-radius: 8px;
            background: #0f9f90;
            color: #ffffff !important;
            font-weight: 800;
            text-decoration: none;
        }

        .email-footer {
            padding: 22px 32px;
            border-top: 6px solid #0f9f90;
            background: #f8fcfd !important;
            color: #334155 !important;
            font-size: 13px;
            text-align: center;
        }

        .footer-logo img {
            display: block;
            width: 72px;
            max-width: 100%;
            height: auto;
            margin: 0 auto 14px;
        }

        .email-footer p {
            margin: 4px 0;
        }

        .email-footer a {
            color: #0f766e;
        }

        @media (max-width: 640px) {
            .email-header,
            .email-body,
            .email-footer {
                padding-left: 20px;
                padding-right: 20px;
            }

            .email-heading {
                font-size: 24px;
            }

            .summary-table th,
            .summary-table td {
                display: block;
                width: auto;
                padding: 10px 14px;
            }

            .summary-table th {
                padding-bottom: 0;
            }
        }
    </style>
</head>
<body>
    <div class="email-shell">
        <div class="email-topbar"></div>
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" bgcolor="#f8fcfd" style="background-color:#f8fcfd !important; background:#f8fcfd !important; color:#102a4c !important; border-bottom:1px solid #dbe7ee !important;">
            <tr>
                <td align="center" bgcolor="#f8fcfd" style="background-color:#f8fcfd !important; background:#f8fcfd !important; padding:28px 32px 18px; text-align:center; color:#102a4c !important;">
                    <img src="{{ $logoUrl }}" alt="Legal Bruz Ltd. logo" width="96" style="display:block; width:96px; max-width:100%; height:auto; margin:0 auto 12px;">
                    <p style="margin:0; color:#5f6e84 !important; font-size:14px; font-weight:700;">@yield('email_title', 'Application update')</p>
                </td>
            </tr>
        </table>

        <div class="email-body">
            @hasSection('status_icon')
                <div class="status-line">@yield('status_icon')</div>
            @endif

            <h1 class="email-heading">@yield('heading')</h1>
            @yield('body')
        </div>

        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" bgcolor="#f8fcfd" style="background-color:#f8fcfd !important; background:#f8fcfd !important; color:#334155 !important; border-top:6px solid #0f9f90 !important;">
            <tr>
                <td align="center" bgcolor="#f8fcfd" style="background-color:#f8fcfd !important; background:#f8fcfd !important; padding:22px 32px; text-align:center; color:#334155 !important;">
                    <img src="{{ $logoUrl }}" alt="Legal Bruz Ltd. logo" width="72" style="display:block; width:72px; max-width:100%; height:auto; margin:0 auto 14px;">
                    <p style="margin:4px 0; color:#334155 !important;">Email: <a href="mailto:{{ $brandEmail }}" style="color:#0f766e !important;">{{ $brandEmail }}</a></p>
                    @if ($brandUrl)
                        <p style="margin:4px 0; color:#334155 !important;">Website: <a href="{{ $brandUrl }}" style="color:#0f766e !important;">{{ $brandUrl }}</a></p>
                    @endif
                    <p style="margin:4px 0; color:#334155 !important;">You are receiving this email because you have an account or active matter with us.</p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
