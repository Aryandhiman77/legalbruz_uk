<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Signature</title>
    <style>
        body {
            font-family: Georgia, "Times New Roman", serif;
            padding: 40px;
            color: #1f2937;
            background: #f8fafc;
        }

        .signature-sheet {
            max-width: 720px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #dbe4f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }

        .eyebrow {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #64748b;
            margin-bottom: 12px;
        }

        .signature-text {
            margin: 28px 0 20px;
            font-size: 42px;
            line-height: 1.2;
            color: #0f172a;
            font-style: italic;
        }

        .meta {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid #e2e8f0;
            font-size: 14px;
            color: #475569;
        }

        .notes {
            margin-top: 18px;
            padding: 14px 16px;
            background: #f8fafc;
            border-radius: 12px;
        }
    </style>
</head>
<body>
    <div class="signature-sheet">
        <div class="eyebrow">Trademark Application Signature</div>
        <h1>Digital Signature Submission</h1>

        <p><strong>Application:</strong> {{ $application->brand_name ?: ('#' . $application->id) }}</p>
        <p><strong>Applicant:</strong> {{ $application->applicant_name ?: $user->name }}</p>

        <div class="signature-text">{{ $signatureText }}</div>

        @if (!empty($signatureNotes))
            <div class="notes">
                <strong>Notes:</strong> {{ $signatureNotes }}
            </div>
        @endif

        <div class="meta">
            Submitted by {{ $user->name }} on {{ $submittedAt->timezone(config('app.timezone', 'Europe/London'))->format('d M Y h:i A T') }}
        </div>
    </div>
</body>
</html>
