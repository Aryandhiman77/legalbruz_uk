@extends('layouts.app')
@section('title', 'Consultation Booked | Legal Bruz')

@section('content')
    <main style="display:grid;place-items:center;min-height:72vh;padding:45px 18px;background:#f3f8fa;">
        <section style="width:min(650px,100%);padding:40px;border:1px solid #dce7ef;border-radius:22px;background:#fff;text-align:center;box-shadow:0 24px 60px rgba(7,31,72,.1);">
            <i class="bi bi-check-circle-fill" style="color:#129486;font-size:3rem;"></i>
            <h1 style="margin:14px 0 8px;color:#102a4c;">Your consultation request is confirmed</h1>
            <p style="margin:0;color:#65778d;line-height:1.7;">Payment of <strong>£{{ number_format((float) $booking->amount, 2) }}</strong> was received. Our team will contact you at <strong>{{ $booking->email }}</strong> to confirm the exact call time.</p>
            <a href="{{ route('landing') }}" style="display:inline-flex;margin-top:24px;padding:13px 22px;border-radius:10px;background:#129486;color:#fff;font-weight:800;text-decoration:none;">Return to homepage</a>
        </section>
    </main>
@endsection
