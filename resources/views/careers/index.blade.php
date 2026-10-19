@extends('layouts.app')
@section('title', 'Careers at Legal Bruz | Open Positions')
@section('meta_description', 'Explore open legal, client success, operations, and growth roles at Legal Bruz.')
@section('canonical_url', route('careers.index'))
@section('og_title', 'Careers at Legal Bruz')
@section('og_description', 'Join the team making intellectual property protection simpler for businesses across the UK.')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/careers.css') }}">

    <div class="careers-page">
        <header class="careers-hero">
            <div class="careers-container careers-hero-grid">
                <div>
                    <span class="careers-eyebrow">Careers at Legal Bruz</span>
                    <h1>Do meaningful work.<br><span>Build what’s next.</span></h1>
                    <p class="careers-hero-copy">Join a team combining legal expertise, thoughtful service, and technology to make intellectual property protection simpler for businesses across the UK.</p>
                </div>
            </div>
        </header>

        <section class="careers-section careers-section-soft" id="open-roles">
            <div class="careers-container">
                <div class="career-section-heading">
                    <div>
                        <span class="careers-eyebrow" style="color:#159f8d;">Open positions</span>
                        <h2>Find a role where you can make an impact</h2>
                    </div>
                    <p>{{ $openJobsCount }} {{ Str::plural('position', $openJobsCount) }} currently open. Every application is reviewed thoughtfully by our team.</p>
                </div>

                <form class="career-filter" method="GET" action="{{ route('careers.index') }}">
                    <div class="career-filter-field career-filter-search">
                        <i class="bi bi-search"></i>
                        <label class="visually-hidden" for="career-search">Search jobs</label>
                        <input id="career-search" class="form-control" type="search" name="search"
                            value="{{ request('search') }}" placeholder="Search roles or locations">
                    </div>
                    <div class="career-filter-field">
                        <i class="bi bi-briefcase"></i>
                        <label class="visually-hidden" for="career-employment">Employment type</label>
                        <select id="career-employment" class="form-select" name="employment">
                            <option value="">All employment types</option>
                            @foreach ($employmentTypes as $type)
                                <option value="{{ $type }}" @selected(request('employment') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="career-filter-field">
                        <i class="bi bi-building"></i>
                        <label class="visually-hidden" for="career-workplace">Workplace type</label>
                        <select id="career-workplace" class="form-select" name="workplace">
                            <option value="">All workplace types</option>
                            @foreach ($workplaceTypes as $type)
                                <option value="{{ $type }}" @selected(request('workplace') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="career-button" type="submit">Search</button>
                    @if (request()->hasAny(['search', 'employment', 'workplace']))
                        <a class="career-filter-clear" href="{{ route('careers.index') }}#open-roles">Clear</a>
                    @endif
                </form>

                <div class="career-jobs">
                    @forelse ($jobs as $job)
                        <article class="career-job-card" data-job-url="{{ route('careers.show', $job) }}" tabindex="0">
                            <span class="career-job-icon"><i class="bi bi-briefcase"></i></span>
                            <span class="career-job-type">{{ $job->workplace_type }}</span>
                            <h3><a href="{{ route('careers.show', $job) }}">{{ $job->title }}</a></h3>
                            <div class="career-job-meta">
                                <span><i class="bi bi-geo-alt"></i>{{ $job->location }}</span>
                                <span><i class="bi bi-clock"></i>{{ $job->employment_type }}</span>
                            </div>
                            <p class="career-job-summary">{{ $job->summary }}</p>
                            <div class="career-job-actions">
                                <a class="career-button career-button-outline" href="{{ route('careers.show', $job) }}">View details</a>
                                <a class="career-button" href="{{ route('careers.apply', $job) }}">Apply now</a>
                            </div>
                        </article>
                    @empty
                        <div class="career-empty">
                            <i class="bi bi-search"></i>
                            <h3>No matching roles right now</h3>
                            <p>Try adjusting your filters or check back soon for new opportunities.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

    </div>

    <script>
        document.querySelectorAll('[data-job-url]').forEach(card => {
            const open = event => {
                if (event.target.closest('a, button')) return;
                window.location.href = card.dataset.jobUrl;
            };
            card.addEventListener('click', open);
            card.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    window.location.href = card.dataset.jobUrl;
                }
            });
        });
    </script>
@endsection
