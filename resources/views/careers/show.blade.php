@extends('layouts.app')
@section('title', $job->title . ' | Careers at Legal Bruz')
@section('meta_description', Str::limit($job->summary, 160, ''))
@section('canonical_url', route('careers.show', $job))
@section('og_title', $job->title . ' | Legal Bruz Careers')
@section('og_description', Str::limit($job->summary, 180, ''))
@section('head')
    <script type="application/ld+json">
        {!! json_encode(array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => $job->title,
            'description' => $job->description,
            'datePosted' => $job->created_at->toDateString(),
            'validThrough' => $job->application_deadline?->endOfDay()->toAtomString(),
            'employmentType' => strtoupper(str_replace('-', '_', $job->employment_type)),
            'hiringOrganization' => [
                '@type' => 'Organization',
                'name' => 'Legal Bruz',
                'sameAs' => route('landing'),
                'logo' => asset('legal-bruz-pvt-ltd-logo.png'),
            ],
            'jobLocationType' => $job->workplace_type === 'Remote' ? 'TELECOMMUTE' : null,
            'jobLocation' => $job->workplace_type !== 'Remote' ? [
                '@type' => 'Place',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => $job->location,
                    'addressCountry' => 'IN',
                ],
            ] : null,
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endsection

@section('content')
    <link rel="stylesheet" href="{{ asset('css/careers.css') }}">

    <div class="careers-page">
        <header class="careers-hero">
            <div class="careers-container career-detail-hero">
                <nav class="career-breadcrumb"><a href="{{ route('careers.index') }}">Careers</a> / {{ $job->title }}</nav>
                <span class="careers-eyebrow">Open position</span>
                <h1>{{ $job->title }}</h1>
                <div class="career-detail-meta">
                    <span><i class="bi bi-geo-alt me-1"></i>{{ $job->location }}</span>
                    <span><i class="bi bi-clock me-1"></i>{{ $job->employment_type }}</span>
                    <span><i class="bi bi-building me-1"></i>{{ $job->workplace_type }}</span>
                    @if ($job->experience_level)<span><i class="bi bi-bar-chart me-1"></i>{{ $job->experience_level }}</span>@endif
                </div>
            </div>
        </header>

        <div class="careers-container career-detail-layout">
            <article class="career-detail-card">
                <h2>About the role</h2>
                <p>{{ $job->description }}</p>

                @if ($job->responsibilities)
                    <h2>What you’ll do</h2>
                    <ul>
                        @foreach (preg_split('/\r\n|\r|\n/', trim($job->responsibilities)) as $item)
                            @if (trim($item)) <li>{{ trim($item) }}</li> @endif
                        @endforeach
                    </ul>
                @endif

                <h2>What we’re looking for</h2>
                <ul>
                    @foreach (preg_split('/\r\n|\r|\n/', trim($job->requirements)) as $item)
                        @if (trim($item)) <li>{{ trim($item) }}</li> @endif
                    @endforeach
                </ul>

                @if ($job->benefits)
                    <h2>What we offer</h2>
                    <ul>
                        @foreach (preg_split('/\r\n|\r|\n/', trim($job->benefits)) as $item)
                            @if (trim($item)) <li>{{ trim($item) }}</li> @endif
                        @endforeach
                    </ul>
                @endif
            </article>

            <aside class="career-detail-aside">
                <h2>Interested in this role?</h2>
                <p>Submit your details and résumé. Our team will review your application carefully.</p>
                <div class="career-aside-row"><small>Location</small><strong>{{ $job->location }}</strong></div>
                <div class="career-aside-row"><small>Work type</small><strong>{{ $job->employment_type }} · {{ $job->workplace_type }}</strong></div>
                @if ($job->salary_range)<div class="career-aside-row"><small>Salary</small><strong>{{ $job->salary_range }}</strong></div>@endif
                @if ($job->application_deadline)<div class="career-aside-row"><small>Apply by</small><strong>{{ $job->application_deadline->format('d M Y') }}</strong></div>@endif
                <a class="career-button" href="{{ route('careers.apply', $job) }}">Apply now <i class="bi bi-arrow-right"></i></a>
            </aside>
        </div>
    </div>
@endsection
