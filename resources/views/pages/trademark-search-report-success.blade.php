@extends('layouts.app')
@section('title', 'Search Report Requested | Legal Bruz')

@section('content')
    <main style="display:grid;place-items:center;min-height:72vh;padding:45px 18px;background:#f3f8fa;">
        <section style="width:min(650px,100%);padding:40px;border:1px solid #dce7ef;border-radius:22px;background:#fff;text-align:center;box-shadow:0 24px 60px rgba(7,31,72,.1);">
            <i class="bi bi-check-circle-fill" style="color:#129486;font-size:3rem;"></i>
            <h1 style="margin:14px 0 8px;color:#102a4c;">Your trademark search report is confirmed</h1>
            <p style="margin:0;color:#65778d;line-height:1.7;">Payment of <strong>£{{ number_format((float) $reportRequest->amount, 2) }}</strong> was received for <strong>{{ $reportRequest->brand_name }}</strong>. You can track the report status from your client dashboard.</p>
            <div style="margin-top:18px;padding:14px;border-radius:12px;background:#eef8f6;color:#126c63;"><strong>Current status:</strong> {{ $reportRequest->report_status_label }}</div>
            @auth
                @if ($reportRequest->documents->isNotEmpty())
                    <div style="display:grid;gap:10px;margin-top:24px;text-align:left;">
                        @foreach ($reportRequest->documents as $document)
                            <a href="{{ route('trademark-search-report.download', [$reportRequest, $document]) }}" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 16px;border-radius:10px;background:#129486;color:#fff;font-weight:800;text-decoration:none;">
                                <span><i class="bi bi-file-earmark-pdf me-2"></i>{{ $document->display_name }}</span>
                                <span>Download</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <a href="{{ route('dashboard') }}" style="display:inline-flex;margin-top:24px;padding:13px 22px;border-radius:10px;background:#129486;color:#fff;font-weight:800;text-decoration:none;">Open client dashboard</a>
                @endif
            @else
                <a href="{{ route('login') }}" style="display:inline-flex;margin-top:24px;padding:13px 22px;border-radius:10px;background:#129486;color:#fff;font-weight:800;text-decoration:none;">Sign in to track status</a>
            @endauth
        </section>
    </main>
@endsection
