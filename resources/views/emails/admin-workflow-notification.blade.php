@extends('emails.layouts.simple')

@section('email_title', $title)
@section('status_icon', '↗')

@section('heading')
    {{ $title }}
@endsection

@section('body')
    <p>Dear Admin,</p>

    <p>{!! nl2br(e($notificationMessage)) !!}</p>

    @if (!empty($actionUrl))
        <div class="cta-wrap">
            <a href="{{ $actionUrl }}" class="primary-button">{{ $actionText ?? 'Review Application' }}</a>
        </div>
    @endif
@endsection
