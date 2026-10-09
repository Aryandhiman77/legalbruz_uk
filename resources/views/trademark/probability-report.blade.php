<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $keyword }} - Trademark Probability Report</title>
    <style>
        @page { margin: 28px 30px; }
        body { margin: 0; color: #1d3557; font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.5; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { margin-bottom: 6px; font-size: 26px; line-height: 1.16; }
        h2 { margin-bottom: 12px; font-size: 16px; }
        .kicker { color: #5f4ee8; font-size: 11px; font-weight: bold; letter-spacing: .04em; text-transform: uppercase; }
        .muted { color: #65748d; }
        .header, .card { border: 1px solid #dfe8f1; border-radius: 12px; padding: 18px; }
        .header { margin-bottom: 14px; background: #f8fbff; }
        .metrics { width: 100%; margin-top: 16px; border-spacing: 10px 0; border-collapse: separate; }
        .metric { width: 33.33%; padding: 16px; border: 1px solid #e3ebf3; border-radius: 10px; background: #ffffff; vertical-align: top; }
        .metric span { display: block; color: #65748d; font-weight: bold; }
        .metric strong { display: block; margin-top: 8px; font-size: 28px; }
        .ok { color: #078d80; }
        .risk { color: #dc454e; }
        .card { margin-top: 12px; page-break-inside: avoid; }
        .summary { background: #f8f7ff; border-color: #dedafe; }
        ul { margin: 0; padding-left: 18px; }
        li { margin-bottom: 8px; }
        .badge { display: inline-block; margin-left: 5px; padding: 2px 6px; border-radius: 999px; background: #eef2f6; color: #526076; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 7px; border-bottom: 1px solid #e5edf4; text-align: left; vertical-align: top; }
        th { color: #526076; background: #f8fafc; font-size: 10px; text-transform: uppercase; }
        .factor-row { margin-bottom: 8px; }
        .factor-label { display: inline-block; width: 190px; color: #526076; }
        .bar { display: inline-block; width: 250px; height: 9px; border-radius: 20px; background: #edf2f7; vertical-align: middle; }
        .bar-fill { display: block; height: 9px; border-radius: 20px; background: #335f8a; }
        .footer-note { margin-top: 10px; padding: 12px; border-radius: 10px; background: #fff8e6; color: #6f5214; }
    </style>
</head>
<body>
    @php
        $insights = $analysis['ai_insights'] ?? [];
        $reasons = $insights['reasons'] ?? [];
        $warnings = $insights['warnings'] ?? [];
        $factors = $analysis['factors'] ?? [];
    @endphp

    <section class="header">
        <div class="kicker">Legal Bruz Ltd. · Trademark Registration Probability</div>
        <h1>{{ $analysis['keyword'] ?? $keyword }}</h1>
        <p class="muted">Generated on {{ $generatedAt }} from {{ count($records) }} trademark search {{ count($records) === 1 ? 'record' : 'records' }}.</p>

        <table class="metrics">
            <tr>
                <td class="metric"><span>Registration Chance</span><strong class="ok">{{ (int) ($analysis['registration_probability'] ?? 0) }}%</strong></td>
                <td class="metric"><span>Conflict Risk</span><strong class="risk">{{ (int) ($analysis['conflict_risk'] ?? 0) }}%</strong></td>
                <td class="metric"><span>Risk Level</span><strong>{{ $analysis['risk_level'] ?? '—' }}</strong></td>
            </tr>
        </table>
    </section>

    <section class="card summary">
        <h2>AI Analysis Summary</h2>
        <p>{{ $insights['summary'] ?? 'No summary was generated for this search.' }}</p>
    </section>

    <section class="card">
        <h2>Important Disclaimer</h2>
        <p>{{ $analysis['disclaimer'] ?? $formalDisclaimer }}</p>
    </section>

    <section class="card">
        <h2>Conflict Counts</h2>
        <table>
            <tr><th>Metric</th><th>Count</th></tr>
            <tr><td>Exact active Word marks</td><td>{{ (int) ($analysis['exact_active_word_marks'] ?? 0) }}</td></tr>
            <tr><td>Exact active Device marks</td><td>{{ (int) ($analysis['exact_active_device_marks'] ?? 0) }}</td></tr>
            <tr><td>Similar active marks</td><td>{{ (int) ($analysis['similar_active_marks'] ?? 0) }}</td></tr>
            <tr><td>Active classes found</td><td>{{ (int) ($analysis['unique_active_classes'] ?? 0) }}</td></tr>
        </table>
    </section>

    <section class="card">
        <h2>Risk Factors</h2>
        @forelse ($factors as $factor)
            @php $score = is_numeric($factor['score'] ?? null) ? max(0, min(100, (float) $factor['score'])) : null; @endphp
            <div class="factor-row">
                <span class="factor-label">{{ $factor['label'] ?? 'Factor' }}</span>
                @if ($score === null)
                    <span class="muted">Not applicable</span>
                @else
                    <span class="bar"><span class="bar-fill" style="width: {{ $score }}%;"></span></span>
                    <strong>{{ (int) $score }}%</strong>
                @endif
            </div>
        @empty
            <p class="muted">No factor data was available.</p>
        @endforelse
    </section>

    <section class="card">
        <h2>Reasons</h2>
        @if (count($reasons))
            <ul>
                @foreach ($reasons as $reason)
                    <li><strong>{{ $reason['title'] ?? 'Reason' }}</strong><span class="badge">{{ $reason['impact'] ?? 'info' }}</span><br>{{ $reason['detail'] ?? '' }}</li>
                @endforeach
            </ul>
        @else
            <p class="muted">No reasons were reported.</p>
        @endif
    </section>

    @if (count($warnings))
        <section class="card">
            <h2>Warnings</h2>
            <ul>
                @foreach ($warnings as $warning)
                    <li><strong>{{ $warning['title'] ?? 'Warning' }}</strong><br>{{ $warning['detail'] ?? '' }}<br><strong>Recommended action:</strong> {{ $warning['action'] ?? '' }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="card">
        <h2>Top Search Records Used</h2>
        @if (count($records))
            <table>
                <tr><th>Application ID</th><th>Trademark</th><th>Status</th><th>Class</th><th>Type</th><th>Proprietor</th></tr>
                @foreach ($records as $record)
                    <tr>
                        <td>{{ $record['application_id'] ?? '' }}</td>
                        <td>{{ $record['trademark_name'] ?? '' }}</td>
                        <td>{{ $record['status'] ?? '' }}</td>
                        <td>{{ $record['class'] ?? '' }}</td>
                        <td>{{ $record['type'] ?? '' }}</td>
                        <td>{{ $record['proprietor'] ?? '' }}</td>
                    </tr>
                @endforeach
            </table>
        @else
            <p class="muted">No trademark records were included in this report.</p>
        @endif
    </section>

    <p class="footer-note">This PDF is generated from the search-data analysis visible on the Legal Bruz trademark search page.</p>
</body>
</html>
