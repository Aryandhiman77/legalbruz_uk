@extends('emails.layouts.simple')

@section('email_title', 'Payment received')
@section('status_icon', '✓')
@section('heading', 'Payment received')

@section('body')
    <p>Dear {{ $recipientName }},</p>
    <p>Your payment for <strong>{{ $serviceLabel }}</strong> has been confirmed successfully.</p>
    <div class="info-box">
        Invoice <strong>{{ $invoice->number }}</strong> is attached to this email as a PDF.
    </div>
    <p>Please retain the attached original invoice for your records.</p>
@endsection
