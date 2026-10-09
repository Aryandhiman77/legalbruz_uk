@extends('layouts.app')
@section('title', 'Contact Legal Bruz | Intellectual Property Support')
@section('meta_description', 'Contact the Legal Bruz team for trademark, intellectual property, application, and service support.')
@section('canonical_url', route('contact'))
@section('og_title', 'Contact Legal Bruz')
@section('og_description', 'Get help with trademarks, intellectual property, and your Legal Bruz matter.')

@section('content')
    @include('pages.partials.styles')
    <div class="public-page contact-page">
        <header class="contact-hero">
            <div class="contact-hero-inner">
                <div>
                    <span class="contact-hero-eyebrow">Get in touch</span>
                    <h1>How can we help?</h1>
                    <p>Tell us what you need and our team will point you in the right direction. For an existing matter, include your application or case number.</p>
                </div>
                <div class="contact-hero-badges" aria-label="Support information">
                    <span><i class="bi bi-clock"></i> One business day response</span>
                    <span><i class="bi bi-shield-check"></i> Your details stay private</span>
                </div>
            </div>
        </header>

        <div class="contact-content">
            @if (session('success'))
                <div class="contact-success" role="status">
                    <i class="bi bi-check-circle-fill"></i>
                    <div><strong>Message received</strong><span>{{ session('success') }}</span></div>
                </div>
            @endif

            <div class="contact-grid">
            <section class="contact-form-card">
                <div class="contact-form-heading">
                    <div>
                        <span class="contact-section-kicker">Send us a message</span>
                        <h2>Let's Protect Your Brand</h2>
                        <p>Complete the form below and a member of our team will get back to you.</p>
                    </div>
                    <span class="contact-form-icon"><i class="bi bi-chat-dots"></i></span>
                </div>
                <form class="public-form" method="POST" action="{{ route('contact.submit') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name">Full Name</label>
                            <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name') }}" maxlength="120" autocomplete="name" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="email">Email Address</label>
                            <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email') }}" maxlength="190" autocomplete="email" required>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="phone">Phone Number</label>
                            <input id="phone" name="phone" type="tel" class="form-control @error('phone') is-invalid @enderror"
                                value="{{ old('phone') }}" maxlength="30" autocomplete="tel" required>
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="business_name">Business Name <span class="text-muted fw-normal">(Optional)</span></label>
                            <input id="business_name" name="business_name" type="text" class="form-control @error('business_name') is-invalid @enderror"
                                value="{{ old('business_name') }}" maxlength="180" autocomplete="organization">
                            @error('business_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="service_interested">Service Interested In</label>
                            @php($currentService = old('service_interested', $selectedContactService ?? null))
                            <select id="service_interested" name="service_interested" class="form-select @error('service_interested') is-invalid @enderror" required>
                                <option value="" disabled @selected(! $currentService)>Select a service</option>
                                @foreach (($contactServices ?? collect(config('visitor_services'))->filter(fn ($service) => $service['contact_visible'] ?? true)->pluck('label')->push('Examination Report')->push('Opposition Service')->push('Other')->all()) as $service)
                                    <option value="{{ $service }}" @selected($currentService === $service)>{{ $service }}</option>
                                @endforeach
                                
                            </select>
                            @error('service_interested') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" class="form-control @error('message') is-invalid @enderror"
                                minlength="10" maxlength="5000" required>{{ old('message') }}</textarea>
                            @error('message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="d-none" aria-hidden="true">
                            <label for="website">Website</label>
                            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                        </div>
                        <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                            <button type="submit" class="contact-submit">Send message <i class="bi bi-arrow-right"></i></button>
                            <small class="text-muted">By submitting, you agree to our <a href="{{ route('privacy') }}">Privacy Policy</a>.</small>
                        </div>
                    </div>
                </form>
            </section>

            <aside class="contact-panel">
                <span class="contact-section-kicker">Contact details</span>
                <h2>Talk to our team</h2>
                <p class="contact-panel-copy">Choose the most convenient way to reach us. We’re available during regular business hours.</p>
                <div class="contact-detail">
                    <span><i class="bi bi-envelope"></i></span>
                    <div><strong>Email</strong><a href="mailto:info@legalbruz.com">info@legalbruz.com</a></div>
                </div>
                <div class="contact-detail">
                    <span><i class="bi bi-geo-alt"></i></span>
                    <div><strong>London Office</strong><span>506-508 woodfield court, Honeypot lane, stanmore- HA7 1JR</span></div>
                </div>
                <div class="contact-detail">
                    <span><i class="bi bi-clock"></i></span>
                    <div><strong>Business Hours</strong><span>Mon to Friday - 10AM to 5PM</span></div>
                </div>
                <div class="contact-help">
                    <i class="bi bi-question-circle"></i>
                    <div><strong>Looking for a quick answer?</strong><a href="{{ route('faq') }}">Browse frequently asked questions <i class="bi bi-arrow-right"></i></a></div>
                </div>
            </aside>
            </div>
        </div>
    </div>
@endsection
