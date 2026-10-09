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
    @php
        $about = $aboutContent ?? \App\Models\CmsPage::aboutContent();
        $aboutImageUrl = fn (string $path) => str_starts_with($path, 'data:')
            ? $path
            : (str_starts_with($path, 'cms/') ? route('storage.public.view', ['path' => $path]) : asset($path));
        $focusItems = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $about['focus_items']))));
    @endphp
    @if ($aboutPreview ?? false)
        <div class="about-preview-banner"><strong>Preview mode</strong><span>These changes have not been saved.</span></div>
    @endif
    <div class="about-page">
        <section class="about-hero">
            <div class="about-shell about-hero-grid">
                <div class="about-hero-copy">
                    <span class="about-eyebrow"><i class="bi bi-shield-check"></i> {{ $about['hero_eyebrow'] }}</span>
                    <h1>{{ $about['hero_title'] }}<br><span>{{ $about['hero_title_accent'] }}</span></h1>
                    {!! $about['hero_copy'] !!}
                    <div class="about-hero-principle">
                        <span>{{ $about['hero_principle_label'] }}</span>
                        <strong>{{ $about['hero_principle_text'] }}</strong>
                    </div>
                </div>
                <div class="about-hero-mark" aria-hidden="true">
                    <span class="about-hero-mark-ring"></span>
                    <img src="{{ $aboutImageUrl($about['hero_image']) }}" alt="">
                    <div class="about-hero-mark-note">
                        <i class="bi bi-patch-check-fill"></i>
                        <span>{{ $about['hero_note_line_1'] }}<br>{{ $about['hero_note_line_2'] }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="about-story-section">
            <div class="about-shell about-story-grid">
                <div>
                    <span class="about-section-kicker">{{ $about['story_kicker'] }}</span>
                    <h2>{{ $about['story_title'] }}</h2>
                </div>
                <div class="about-story-copy">
                    {!! $about['story_copy'] !!}
                </div>
            </div>
        </section>

        <section class="about-direction-section">
            <div class="about-shell about-direction-grid">
                <article class="about-direction-card">
                    <span class="about-direction-icon"><i class="bi bi-bullseye"></i></span>
                    <h2>{{ $about['mission_title'] }}</h2>
                    {!! $about['mission_copy'] !!}
                </article>
                <article class="about-direction-card is-vision">
                    <span class="about-direction-icon"><i class="bi bi-eye"></i></span>
                    <h2>{{ $about['vision_title'] }}</h2>
                    {!! $about['vision_copy'] !!}
                </article>
            </div>
        </section>

        <section class="about-founder-section">
            <div class="about-shell">
                <div class="about-founder-grid">
                    <figure class="about-founder-portrait">
                        <img src="{{ $aboutImageUrl($about['founder_image']) }}" alt="{{ $about['founder_name'] }}, {{ $about['founder_role'] }}" width="1122" height="1402">
                        <figcaption>
                            <strong>{{ $about['founder_name'] }}</strong>
                            <span>{{ $about['founder_role'] }}</span>
                        </figcaption>
                    </figure>
                    <div class="about-founder-copy">
                        <span class="about-section-kicker">{{ $about['founder_kicker'] }}</span>
                        <h2>{{ $about['founder_title'] }}</h2>
                        {!! $about['founder_copy'] !!}
                        <div class="about-focus">
                            <h3>{{ $about['focus_heading'] }}</h3>
                            <div class="about-focus-list">
                                @foreach ($focusItems as $focus)
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
                    <span class="about-section-kicker">{{ $about['trust_kicker'] }}</span>
                    <h2>{{ $about['trust_title'] }}</h2>
                </div>
                <div class="about-trust-grid">
                    @foreach ([
                        ['icon' => 'bi-person-heart', 'title' => $about['trust_1_title'], 'copy' => $about['trust_1_copy']],
                        ['icon' => 'bi-layout-text-window-reverse', 'title' => $about['trust_2_title'], 'copy' => $about['trust_2_copy']],
                        ['icon' => 'bi-cpu', 'title' => $about['trust_3_title'], 'copy' => $about['trust_3_copy']],
                        ['icon' => 'bi-headset', 'title' => $about['trust_4_title'], 'copy' => $about['trust_4_copy']],
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
                    <span class="about-section-kicker">{{ $about['cta_kicker'] }}</span>
                    <h2>{{ $about['cta_title'] }}</h2>
                    {!! $about['cta_copy'] !!}
                </div>
                <a href="{{ route('contact') }}">{{ $about['cta_label'] }} <i class="bi bi-arrow-right"></i></a>
            </div>
        </section>
    </div>
@endsection
