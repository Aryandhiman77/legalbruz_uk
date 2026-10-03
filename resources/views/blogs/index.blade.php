@extends('layouts.app')

@section('title', 'Legal Insights & IP Guides | Legal Bruz')
@section('meta_description', 'Practical trademark, intellectual property, and business protection guides from the Legal Bruz team.')
@section('canonical_url', route('blog.index'))
@section('og_title', 'Legal Insights & IP Guides | Legal Bruz')
@section('og_description', 'Practical guides to trademarks, intellectual property, and protecting your business.')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/blog.css') }}?v=20260729-3">
    <div class="blog-page">
        <header class="blog-hero">
            <div class="blog-container blog-hero-inner">
                <span class="blog-eyebrow">Legal Bruz Insights</span>
                <h1>Blogs</h1>
                <p>Practical articles on trademarks, intellectual property, and the legal steps that help businesses grow with confidence.</p>
            </div>
        </header>

        <main class="blog-container blog-main">
            <form class="blog-filter" method="GET" action="{{ route('blog.index') }}">
                <input class="form-control" name="search" type="search" value="{{ request('search') }}" placeholder="Search articles">
                <select class="form-select" name="category">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->category }}" @selected(request('category') === $category->category)>{{ $category->category }} ({{ $category->posts_count }})</option>
                    @endforeach
                </select>
                <button class="blog-button"><i class="bi bi-search"></i> Search</button>
            </form>

            @if ($featuredPost && !request()->hasAny(['search','category']))
                <article class="blog-featured">
                    <a class="blog-card-link"
                        href="{{ route('blog.show', ['blog' => $featuredPost->slug]) }}"
                        aria-label="Read {{ $featuredPost->title }}"></a>
                    <div class="blog-featured-media">
                        @if ($featuredPost->featured_image_path)
                            <img src="{{ route('storage.public.view', ['path' => $featuredPost->featured_image_path]) }}" alt="{{ $featuredPost->featured_image_alt ?: $featuredPost->title }}">
                        @else
                            <div class="blog-image-placeholder"><i class="bi bi-journal-richtext"></i></div>
                        @endif
                    </div>
                    <div class="blog-featured-content">
                        <span class="blog-label">Featured · {{ $featuredPost->category }}</span>
                        <h2>{{ $featuredPost->title }}</h2>
                        <p>{{ $featuredPost->excerpt }}</p>
                        <div class="blog-meta">
                            <span><i class="bi bi-person"></i>{{ $featuredPost->author_name }}</span>
                            <span><i class="bi bi-calendar3"></i>{{ $featuredPost->published_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</span>
                            <span><i class="bi bi-clock"></i>{{ $featuredPost->reading_time }} min read</span>
                        </div>
                    </div>
                </article>
            @endif

            <div class="blog-section-heading">
                <h2>{{ request()->hasAny(['search','category']) ? 'Search results' : 'Latest articles' }}</h2>
                <span>{{ $posts->total() }} {{ Str::plural('article', $posts->total()) }}</span>
            </div>
            <div class="blog-grid">
                @forelse ($posts as $post)
                    <article class="blog-card">
                        <a class="blog-card-link"
                            href="{{ route('blog.show', ['blog' => $post->slug]) }}"
                            aria-label="Read {{ $post->title }}"></a>
                        <div class="blog-card-media">
                            @if ($post->featured_image_path)
                                <img src="{{ route('storage.public.view', ['path' => $post->featured_image_path]) }}" alt="{{ $post->featured_image_alt ?: $post->title }}" loading="lazy">
                            @else
                                <div class="blog-image-placeholder"><i class="bi bi-journal-text"></i></div>
                            @endif
                        </div>
                        <div class="blog-card-body">
                            <span class="blog-label">{{ $post->category }}</span>
                            <h2>{{ $post->title }}</h2>
                            <p>{{ Str::limit($post->excerpt, 130) }}</p>
                            <div class="blog-meta">
                                <span>{{ $post->published_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</span>
                                <span>{{ $post->reading_time }} min read</span>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="blog-empty">
                        <h2>{{ request()->hasAny(['search','category']) ? 'No matching articles' : 'No articles published yet' }}</h2>
                        <p>{{ request()->hasAny(['search','category']) ? 'Try changing or clearing the current filters.' : 'New legal insights will appear here soon.' }}</p>
                        @if (request()->hasAny(['search','category']))
                            <a class="blog-button" href="{{ route('blog.index') }}">Clear filters</a>
                        @endif
                    </div>
                @endforelse
            </div>
            <div class="mt-4">{{ $posts->links() }}</div>
        </main>
    </div>
@endsection
