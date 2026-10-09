@extends('layouts.app')
@section('title', 'Privacy Policy | Legal Bruz')
@section('meta_description', 'Learn how Legal Bruz Ltd. collects, uses, stores, shares, and protects personal data.')
@section('canonical_url', route('privacy'))
@section('og_title', 'Privacy Policy | Legal Bruz')
@section('og_description', 'How Legal Bruz Ltd. handles and protects personal data.')

@section('content')
    @include('pages.partials.styles')
    <div class="public-page legal-page">
        <header class="legal-hero">
            <div class="legal-hero-inner">
                <span class="legal-hero-eyebrow">Your Information</span>
                <h1>{{ $legalPage['title'] ?? 'Privacy Policy' }}</h1>
                <p class="legal-hero-copy">This policy explains how Legal Bruz Ltd. collects, uses, stores, shares, and protects personal data.</p>
            </div>
        </header>

        <div class="legal-content">
            <article class="legal-card">
                <div class="privacy-policy-text">{!! $legalPage['content'] ?? '' !!}</div>
            </article>
        </div>
    </div>

    <style>
        .privacy-policy-text {
            white-space: pre-wrap;
            color: #26364f;
            font-size: 1rem;
            line-height: 1.8;
        }
        .privacy-policy-text :is(p, ul, ol, blockquote, table) {
            margin-bottom: 1rem;
        }
    </style>
@endsection
