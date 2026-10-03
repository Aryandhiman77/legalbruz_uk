@extends('layouts.app')

@php
    $canonical = $blog->canonical_url ?: route('blog.show', $blog);
    $socialImage = $blog->og_image_path
        ? route('storage.public.view', ['path' => $blog->og_image_path])
        : ($blog->featured_image_path ? route('storage.public.view', ['path' => $blog->featured_image_path]) : asset('legal-bruz-pvt-ltd-logo.png'));
@endphp

@section('title', $blog->meta_title . ' | Legal Bruz')
@section('meta_description', $blog->meta_description)
@section('canonical_url', $canonical)
@section('og_type', 'article')
@section('og_title', $blog->og_title ?: $blog->meta_title)
@section('og_description', $blog->og_description ?: $blog->meta_description)
@section('og_image', $socialImage)
@section('head')
    @if ($blog->seo_keywords)<meta name="keywords" content="{{ $blog->seo_keywords }}">@endif
    <meta property="article:published_time" content="{{ $blog->published_at->toAtomString() }}">
    <meta property="article:modified_time" content="{{ $blog->updated_at->toAtomString() }}">
    <meta property="article:author" content="{{ $blog->author_name }}">
    <meta property="article:section" content="{{ $blog->category }}">
    @foreach ($blog->tags ?? [] as $tag)<meta property="article:tag" content="{{ $tag }}">@endforeach
    @if ($blog->schema_markup)
        <script type="application/ld+json">{!! str_ireplace('</script', '<\\/script', $blog->schema_markup) !!}</script>
    @endif
@endsection

@section('content')
    <link rel="stylesheet" href="{{ asset('css/blog.css') }}">
    <div class="blog-page">
        <header class="blog-hero">
            <div class="blog-container article-hero">
                <nav class="article-breadcrumb"><a href="{{ route('blog.index') }}">Insights</a> / {{ $blog->category }}</nav>
                <span class="blog-eyebrow">{{ $blog->category }}</span>
                <h1>{{ $blog->title }}</h1>
                <div class="blog-meta">
                    <span><i class="bi bi-person"></i>{{ $blog->author_name }}</span>
                    <span><i class="bi bi-calendar3"></i>{{ $blog->published_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</span>
                    <span><i class="bi bi-clock"></i>{{ $blog->reading_time }} min read</span>
                </div>
            </div>
        </header>

        <div class="blog-container article-layout">
            <article class="article-card">
                @if ($blog->featured_image_path)
                    <img class="article-cover" src="{{ route('storage.public.view', ['path' => $blog->featured_image_path]) }}" alt="{{ $blog->featured_image_alt ?: $blog->title }}">
                @endif
                <div class="article-content">
                    @if (preg_match('/<[a-z][\s\S]*>/i', $blog->content))
                        {!! $blog->content !!}
                    @else
                        {!! Str::markdown($blog->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                    @endif
                </div>
            </article>
            <aside class="article-aside">
                <div class="article-aside-card">
                    <h2>About this article</h2>
                    <p>{{ $blog->excerpt }}</p>
                    @if ($blog->tags)
                        <div class="article-tags">@foreach ($blog->tags as $tag)<span>{{ $tag }}</span>@endforeach</div>
                    @endif
                </div>
                @if ($relatedPosts->isNotEmpty())
                    <div class="article-aside-card">
                        <h2>Related articles</h2>
                        @foreach ($relatedPosts as $related)
                            <a class="related-post" href="{{ route('blog.show', $related) }}">{{ $related->title }}</a>
                        @endforeach
                    </div>
                @endif
            </aside>
        </div>
    </div>
@endsection
