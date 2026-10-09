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
            padding: 24px;
            background: #f3f5f9;
            color: #1f2937;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.55;
        }

        .email-shell {
            max-width: 720px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #dbe4ef;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }

        .email-hero {
            background: #f8fcfd !important;
            border-bottom: 6px solid #0f9f90 !important;
            padding: 30px 38px;
            color: #082653 !important;
        }

        .brand-row {
            width: 100%;
            text-align: center;
        }

        .brand-logo img {
            display: block;
            width: 96px;
            max-width: 100%;
            height: auto;
            margin: 0 auto;
        }

        .email-body {
            padding: 34px 38px 28px;
        }

        .status-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 18px;
            border-radius: 999px;
            display: table;
            background: #eefbf6;
            border: 1px solid #bdebd8;
            color: #0f9461;
            text-align: center;
            font-size: 34px;
            font-weight: 800;
        }

        .status-icon span {
            display: table-cell;
            vertical-align: middle;
        }

        .email-heading {
            margin: 0;
            color: #082653;
            text-align: center;
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 30px;
            line-height: 1.25;
        }

        .email-heading strong {
            color: #0f9461;
            font-weight: 800;
        }

        .heading-rule {
            width: 54px;
            height: 2px;
            margin: 18px auto 28px;
            background: #f2b84b;
        }

        .email-body p {
            font-size: 16px;
        }

        .summary-card {
            margin: 24px 0;
            border: 1px solid #d5dfeb;
            border-radius: 8px;
            overflow: hidden;
            background: #ffffff;
        }

        .summary-title {
            padding: 14px 18px;
            background: #f8fbff;
            color: #082653;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border-bottom: 1px solid #d5dfeb;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table th,
        .summary-table td {
            padding: 12px 18px;
            border-bottom: 1px solid #edf2f7;
            text-align: left;
            font-size: 15px;
        }

        .summary-table tr:last-child th,
        .summary-table tr:last-child td {
            border-bottom: 0;
        }

        .summary-table th {
            width: 42%;
            color: #082653;
            font-weight: 800;
        }

        .summary-table td {
            color: #1f2937;
        }

        .status-pill {
            display: inline-block;
            border: 1px solid #9ee6bd;
            border-radius: 7px;
            background: #eefbf4;
            color: #0f6b45;
            padding: 5px 10px;
            font-size: 13px;
            font-weight: 800;
        }

        .status-pill.warning {
            border-color: #f6d38b;
            background: #fff7e6;
            color: #8a4b00;
        }

        .status-pill.danger {
            border-color: #fecaca;
            background: #fff1f2;
            color: #991b1b;
        }

        .admin-note {
            margin: 22px 0;
            padding: 14px 16px;
            border: 1px solid #fbbf24;
            border-left: 4px solid #f59e0b;
            border-radius: 8px;
            background: #fffbeb;
            color: #78350f;
        }

        .admin-note strong {
            color: #92400e;
        }

        .cta-wrap {
            margin: 28px 0 10px;
            text-align: center;
        }

        .primary-button {
            display: inline-block;
            min-width: 260px;
            padding: 14px 24px;
            border-radius: 7px;
            background: #082f60;
            color: #ffffff !important;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-decoration: none;
            text-transform: uppercase;
        }

        .email-footer {
            background: #f8fcfd !important;
            color: #334155 !important;
            border-top: 6px solid #0f9f90 !important;
            padding: 28px 38px;
        }

        .footer-grid {
            width: 100%;
            text-align: center;
        }

        .footer-logo img {
            width: 72px;
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto 18px;
        }

        .footer-contact {
            text-align: center;
        }

        .footer-contact strong {
            display: block;
            color: #0f766e;
            margin-bottom: 8px;
        }

        .footer-contact p {
            margin: 5px 0;
            color: #334155 !important;
            font-size: 14px;
        }

        .copyright {
            padding: 16px 38px 22px;
            color: #6b7280;
            font-size: 12px;
            text-align: center;
            background: #ffffff;
        }

        @media (max-width: 640px) {
            body {
                padding: 12px;
            }

            .email-hero,
            .email-body,
            .email-footer,
            .copyright {
                padding-left: 20px;
                padding-right: 20px;
            }

            .brand-logo img,
            .footer-logo img {
                width: 82px;
            }

            .email-heading {
                font-size: 25px;
            }

            .summary-table th,
            .summary-table td {
                display: block;
                width: auto;
                padding: 10px 14px;
            }

            .summary-table th {
                padding-bottom: 0;
                border-bottom: 0;
            }

            .primary-button {
                min-width: 0;
                width: 100%;
                box-sizing: border-box;
            }
        }
    </style>
</head>
<body>
    <div class="email-shell">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" bgcolor="#f8fcfd" style="background-color:#f8fcfd !important; background:#f8fcfd !important; color:#082653 !important; border-bottom:6px solid #0f9f90 !important;">
            <tr>
                <td align="center" bgcolor="#f8fcfd" style="background-color:#f8fcfd !important; background:#f8fcfd !important; padding:30px 38px;">
                    <img src="{{ $logoUrl }}" alt="Legal Bruz Ltd. logo" width="96" style="display:block; width:96px; max-width:100%; height:auto; margin:0 auto;">
                </td>
            </tr>
        </table>

        <div class="email-body">
            @hasSection('status_icon')
                <div class="status-icon"><span>@yield('status_icon')</span></div>
            @endif

            <h1 class="email-heading">@yield('heading')</h1>
            <div class="heading-rule"></div>

            @yield('body')
        </div>

        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" bgcolor="#f8fcfd" style="background-color:#f8fcfd !important; background:#f8fcfd !important; color:#334155 !important; border-top:6px solid #0f9f90 !important;">
            <tr>
                <td align="center" bgcolor="#f8fcfd" style="background-color:#f8fcfd !important; background:#f8fcfd !important; padding:28px 38px; color:#334155 !important;">
                    <img src="{{ $logoUrl }}" alt="Legal Bruz Ltd. logo" width="72" style="display:block; width:72px; max-width:100%; height:auto; margin:0 auto 18px;">
                    <strong style="display:block; color:#0f766e !important; margin-bottom:8px;">Contact Us</strong>
                    <p style="margin:5px 0; color:#334155 !important; font-size:14px;">{{ $brandEmail }}</p>
                    <p style="margin:5px 0; color:#334155 !important; font-size:14px;">{{ $brandUrl }}</p>
                </td>
            </tr>
        </table>

        <div class="copyright">
            © {{ now()->year }}. All rights reserved.<br>
            You are receiving this email because you have registered with us.
        </div>
    </div>
</body>
</html>
