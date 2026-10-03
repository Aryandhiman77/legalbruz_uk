@extends('layouts.app')
@section('title', 'About Legal Bruz | Brand Protection Made Simple')
@section('meta_description', 'Meet Legal Bruz and founder Anshul Sharma. Learn how our legal-tech platform makes trademark registration and intellectual property protection clearer and more accessible.')
@section('canonical_url', route('about'))
@section('og_title', 'About Legal Bruz')
@section('og_description', 'Protecting brands and empowering founders through accessible, technology-enabled intellectual property solutions.')
@section('og_image', asset('anshul-sharma-founder.png'))
@section('body_class', 'about-body')

@section('head')
    <link rel="stylesheet" href="{{ asset('css/about.css') }}?v={{ filemtime(public_path('css/about.css')) }}">
@endsection

@section('content')
    <div class="about-page">
        <section class="about-hero">
            <div class="about-shell about-hero-grid">
                <div class="about-hero-copy">
                    <span class="about-eyebrow"><i class="bi bi-shield-check"></i> About Legal Bruz</span>
                    <h1>Protecting Brands.<br><span>Empowering Founders.</span></h1>
                    <p>Legal Bruz is a modern legal-tech platform built to simplify trademark registration, intellectual property protection, and brand legal services for startups, entrepreneurs, and growing businesses.</p>
                    <div class="about-hero-principle">
                        <span>Our goal is simple</span>
                        <strong>Make brand protection faster, clearer, and more accessible.</strong>
                    </div>
                </div>
                <div class="about-hero-mark" aria-hidden="true">
                    <span class="about-hero-mark-ring"></span>
                    <img src="{{ asset('legal-bruz-pvt-ltd-logo.png') }}" alt="">
                    <div class="about-hero-mark-note">
                        <i class="bi bi-patch-check-fill"></i>
                        <span>Built for brands.<br>Backed by law.</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="about-story-section">
            <div class="about-shell about-story-grid">
                <div>
                    <span class="about-section-kicker">Our story</span>
                    <h2>Legal protection should not be confusing or expensive to understand.</h2>
                </div>
                <div class="about-story-copy">
                    <p>Most founders spend months building a brand before thinking about protecting it. Unfortunately, that’s often when legal problems begin.</p>
                    <p>We created Legal Bruz to bridge the gap between traditional legal services and modern technology, helping businesses secure their brands with confidence.</p>
                    <p>Whether you’re launching your first startup or managing an established business, our mission is to make intellectual property protection straightforward, transparent, and reliable.</p>
                </div>
            </div>
        </section>

        <section class="about-direction-section">
            <div class="about-shell about-direction-grid">
                <article class="about-direction-card">
                    <span class="about-direction-icon"><i class="bi bi-bullseye"></i></span>
                    <h2>Our Mission</h2>
                    <p>To help founders and businesses protect their brands through accessible, technology-enabled intellectual property and trademark solutions.</p>
                </article>
                <article class="about-direction-card is-vision">
                    <span class="about-direction-icon"><i class="bi bi-eye"></i></span>
                    <h2>Our Vision</h2>
                    <p>To become the trusted legal-tech platform that makes brand protection simple, efficient, and accessible for every entrepreneur.</p>
                </article>
            </div>
        </section>

        <section class="about-founder-section">
            <div class="about-shell">
                <div class="about-founder-grid">
                    <figure class="about-founder-portrait">
                        <img src="{{ asset('anshul-sharma-founder.png') }}" alt="Anshul Sharma, Founder of Legal Bruz" width="1122" height="1402">
                        <figcaption>
                            <strong>Anshul Sharma</strong>
                            <span>Founder, Legal Bruz</span>
                        </figcaption>
                    </figure>
                    <div class="about-founder-copy">
                        <span class="about-section-kicker">Meet the founder</span>
                        <h2>Legal expertise with a founder-first mindset.</h2>
                        <p>Anshul Sharma is the founder of Legal Bruz and has experience in intellectual property law, trademark prosecution, and brand protection. His work has involved assisting businesses with trademark filings, portfolio management, and protecting valuable intellectual property.</p>
                        <p>Legal Bruz was founded with a clear vision—to make trademark and intellectual property services easier to understand and more accessible for startups and growing businesses through a combination of legal expertise and technology.</p>
                        <div class="about-focus">
                            <h3>Areas of focus</h3>
                            <div class="about-focus-list">
                                @foreach ([
                                    'Trademark Registration',
                                    'Trademark Searches',
                                    'Trademark Opposition',
                                    'Intellectual Property Strategy',
                                    'Brand Protection',
                                    'Copyright',
                                    'Startup Legal Guidance',
                                ] as $focus)
                                    <span><i class="bi bi-check2"></i>{{ $focus }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="about-trust-section">
            <div class="about-shell">
                <div class="about-section-heading">
                    <span class="about-section-kicker">Why trust Legal Bruz?</span>
                    <h2>Clear support at every step of your trademark journey.</h2>
                </div>
                <div class="about-trust-grid">
                    @foreach ([
                        ['icon' => 'bi-person-heart', 'title' => 'Founder-first approach', 'copy' => 'Practical guidance shaped around the realities of building and growing a brand.'],
                        ['icon' => 'bi-layout-text-window-reverse', 'title' => 'Transparent process', 'copy' => 'Clear stages, understandable actions, and visibility throughout your matter.'],
                        ['icon' => 'bi-cpu', 'title' => 'Technology-enabled', 'copy' => 'Modern tools that make legal services easier to access, follow, and manage.'],
                        ['icon' => 'bi-headset', 'title' => 'Dedicated support', 'copy' => 'Consistent assistance throughout your trademark and brand-protection journey.'],
                    ] as $item)
                        <article class="about-trust-card">
                            <span><i class="bi {{ $item['icon'] }}"></i></span>
                            <h3>{{ $item['title'] }}</h3>
                            <p>{{ $item['copy'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="about-cta">
            <div class="about-shell about-cta-inner">
                <div>
                    <span class="about-section-kicker">Let’s protect your brand</span>
                    <h2>Build with confidence. Protect what makes your business distinct.</h2>
                    <p>Whether you’re starting a business, launching a new brand, or protecting an existing one, we’re here to help.</p>
                </div>
                <a href="{{ route('contact') }}">Contact our team <i class="bi bi-arrow-right"></i></a>
            </div>
        </section>
    </div>
@endsection
