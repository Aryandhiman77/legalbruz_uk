@extends('layouts.app')
@section('title', 'Trademark Search Report | Legal Bruz')
@section('meta_description', 'Request a UK trademark search report with conflict observations for your proposed brand.')
@section('canonical_url', route('trademark-search-report.create'))
@section('og_title', 'Trademark Search Report | Legal Bruz')
@section('og_description', 'Tell us about your brand and receive a structured trademark search report.')

@section('content')
    @include('pages.partials.styles')
    <div class="public-page contact-page trademark-report-page">
        <header class="contact-hero">
            <div class="contact-hero-inner">
                <div>
                    <span class="contact-hero-eyebrow">Search before filing</span>
                    <h1>Trademark Search Report</h1>
                    <p>Share your proposed brand and business activity, then complete the secure fixed-fee payment to begin the search.</p>
                </div>
                <div class="contact-hero-badges" aria-label="Report information">
                    <span><i class="bi bi-search"></i> Structured register search</span>
                    <span><i class="bi bi-shield-check"></i> Confidential submission</span>
                    <span><i class="bi bi-credit-card"></i> £{{ number_format($reportFee, 2) }} fixed fee</span>
                </div>
            </div>
        </header>

        <div class="contact-content">
            @if (session('success'))
                <div class="contact-success" role="status">
                    <i class="bi bi-check-circle-fill"></i>
                    <div><strong>Request received</strong><span>{{ session('success') }}</span></div>
                </div>
            @endif

            <section class="contact-form-card trademark-report-card">
                <div class="contact-form-heading">
                    <div>
                        <span class="contact-section-kicker">Report request</span>
                        <h2>Tell us about your brand</h2>
                        <p>Complete all fields so the search can be scoped to the right goods and services.</p>
                    </div>
                    <span class="contact-form-icon"><i class="bi bi-file-earmark-text"></i></span>
                </div>

                <form class="public-form" method="POST" action="{{ route('trademark-search-report.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name">Full Name</label>
                            <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" maxlength="120" autocomplete="name" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="email">Email Address</label>
                            <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" maxlength="190" autocomplete="email" required>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="phone">Phone Number</label>
                            <input id="phone" name="phone" type="tel" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" maxlength="30" autocomplete="tel" required>
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="brand_name">Brand Name</label>
                            <input id="brand_name" name="brand_name" type="text" class="form-control @error('brand_name') is-invalid @enderror" value="{{ old('brand_name') }}" maxlength="180" required>
                            @error('brand_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="business_activity">Goods, Services or Business Activity</label>
                            <textarea id="business_activity" name="business_activity" class="form-control @error('business_activity') is-invalid @enderror" minlength="10" maxlength="5000" placeholder="Describe the products or services the brand will cover." required>{{ old('business_activity') }}</textarea>
                            @error('business_activity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="d-none" aria-hidden="true">
                            <label for="website">Website</label>
                            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                        </div>
                        <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                            <button type="submit" class="contact-submit">Continue to payment · £{{ number_format($reportFee, 2) }} <i class="bi bi-arrow-right"></i></button>
                            <small class="text-muted">By submitting, you agree to our <a href="{{ route('privacy') }}">Privacy Policy</a>.</small>
                        </div>
                    </div>
                </form>
            </section>
        </div>
    </div>

    <style>
        .trademark-report-card{max-width:920px;margin:0 auto}
    </style>
@endsection
