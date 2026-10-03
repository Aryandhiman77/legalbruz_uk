@php
    $articleContent = old('content', $blog->content);
    $editorContent = $articleContent && ! preg_match('/<[a-z][\s\S]*>/i', $articleContent)
        ? Str::markdown($articleContent, ['html_input' => 'strip', 'allow_unsafe_links' => false])
        : $articleContent;
@endphp

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1" style="color:#1D3557;">{{ $title }}</h1>
            <p class="text-muted mb-0">Write the article, control publishing, and configure its search and social metadata.</p>
        </div>
        <div class="d-flex gap-2">
            @if ($blog->exists && $blog->is_public)<a href="{{ route('blog.show', $blog) }}" target="_blank" class="btn btn-outline-primary btn-sm">View Article</a>@endif
            <a href="{{ route('admin.blogs.index') }}" class="btn btn-outline-secondary btn-sm">Back to Blogs</a>
        </div>
    </div>

    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

    @if ($errors->any())
        <div class="alert alert-danger mb-3" role="alert">
            <strong>Please correct the highlighted fields.</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="blogEditorForm" method="POST" action="{{ $action }}" enctype="multipart/form-data"
        class="blog-editor-form" data-loading-text="{{ $submitLabel === 'Create Blog Post' ? 'Creating blog post...' : 'Saving changes...' }}">
        @csrf
        @if ($method !== 'POST') @method($method) @endif

        <div class="card blog-form-shell border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-3 blog-form-grid">
                    <div class="col-xl-8">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white p-3"><h2 class="h5 mb-0">Article content</h2></div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label for="title" class="form-label fw-bold">Article title</label>
                            <input id="title" name="title" class="form-control @error('title') is-invalid @enderror"
                                value="{{ old('title', $blog->title) }}" maxlength="255" required>
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label for="excerpt" class="form-label fw-bold">Excerpt</label>
                            <textarea id="excerpt" name="excerpt" rows="3" maxlength="1000"
                                class="form-control @error('excerpt') is-invalid @enderror" required>{{ old('excerpt', $blog->excerpt) }}</textarea>
                            <div class="form-text">A concise summary used on listing cards and as the fallback SEO description.</div>
                            @error('excerpt') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-0">
                            <label for="content" class="form-label fw-bold">Article body</label>
                            <textarea id="content" name="content" rows="22"
                                class="form-control @error('content') is-invalid @enderror"
                                maxlength="100000">{{ $editorContent }}</textarea>
                            <div class="form-text">Use the editor toolbar to add headings, formatting, links, lists, quotes, and tables.</div>
                            @error('content') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white p-3 d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0">Search and social SEO</h2>
                        <span class="badge bg-success">SEO ready</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-light border small">
                            Leave optional fields blank to use the article title and excerpt automatically.
                        </div>
                        <div class="mb-3">
                            <label for="seo_title" class="form-label fw-bold">SEO title</label>
                            <input id="seo_title" name="seo_title" class="form-control" maxlength="70"
                                value="{{ old('seo_title', $blog->seo_title) }}" data-count-target="seo-title-count">
                            <div class="form-text"><span id="seo-title-count">0</span>/70 characters. Aim for approximately 50–60.</div>
                        </div>
                        <div class="mb-3">
                            <label for="seo_description" class="form-label fw-bold">Meta description</label>
                            <textarea id="seo_description" name="seo_description" rows="3" class="form-control"
                                maxlength="170" data-count-target="seo-description-count">{{ old('seo_description', $blog->seo_description) }}</textarea>
                            <div class="form-text"><span id="seo-description-count">0</span>/170 characters. Aim for approximately 140–160.</div>
                        </div>
                        <div class="mb-3">
                            <label for="seo_keywords" class="form-label fw-bold">SEO keywords</label>
                            <input id="seo_keywords" name="seo_keywords" class="form-control" maxlength="500"
                                value="{{ old('seo_keywords', $blog->seo_keywords) }}" placeholder="trademark registration, brand protection">
                            <div class="form-text">Optional comma-separated phrases. Search engines primarily use the title, description, and content.</div>
                        </div>
                        <div class="mb-3">
                            <label for="canonical_url" class="form-label fw-bold">Canonical URL</label>
                            <input id="canonical_url" name="canonical_url" type="url" class="form-control" maxlength="500"
                                value="{{ old('canonical_url', $blog->canonical_url) }}" placeholder="{{ $blog->exists ? route('blog.show', $blog) : 'Automatically generated' }}">
                            <div class="form-text">Only set this when another URL should be treated as the original version.</div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="og_title" class="form-label fw-bold">Social sharing title</label>
                                <input id="og_title" name="og_title" class="form-control" maxlength="100" value="{{ old('og_title', $blog->og_title) }}">
                            </div>
                            <div class="col-md-6">
                                <label for="og_description" class="form-label fw-bold">Social sharing description</label>
                                <input id="og_description" name="og_description" class="form-control" maxlength="220" value="{{ old('og_description', $blog->og_description) }}">
                            </div>
                            <div class="col-12">
                                <label for="og_image" class="form-label fw-bold">Social sharing image</label>
                                <input id="og_image" name="og_image" type="file" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                                <div class="form-text">Recommended: 1200 × 630 pixels, up to 5 MB. Falls back to the featured image.</div>
                                @if ($blog->og_image_path)
                                    <div class="mt-2 d-flex align-items-center gap-3">
                                        <img src="{{ route('storage.public.view', ['path' => $blog->og_image_path]) }}" alt="" style="width:130px;height:68px;object-fit:cover;border-radius:7px;">
                                        <label class="form-check"><input class="form-check-input" type="checkbox" name="remove_og_image" value="1"> Remove image</label>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <hr class="my-4">
                        <div>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <label for="schema_markup" class="form-label fw-bold mb-0">Custom schema markup</label>
                                <span class="badge bg-secondary">Manual JSON-LD</span>
                            </div>
                            <textarea id="schema_markup" name="schema_markup" rows="14"
                                class="form-control font-monospace @error('schema_markup') is-invalid @enderror"
                                maxlength="50000"
                                placeholder='{
  "&#64;context": "https://schema.org",
  "&#64;type": "BlogPosting",
  "headline": "Your article title"
}'>{{ old('schema_markup', $blog->schema_markup) }}</textarea>
                            <div class="form-text">
                                Paste a complete valid JSON object only. Do not include
                                <code>&lt;script type="application/ld+json"&gt;</code> tags; the website adds them automatically.
                                Leave blank to output no schema for this article.
                            </div>
                            @error('schema_markup') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                    </div>

                    <div class="col-xl-4">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white p-3"><h2 class="h5 mb-0">Publish</h2></div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label for="status" class="form-label fw-bold">Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="draft" @selected(old('status', $blog->status) === 'draft')>Draft</option>
                                <option value="published" @selected(old('status', $blog->status) === 'published')>Published</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="published_at" class="form-label fw-bold">Publish date and time</label>
                            <input id="published_at" name="published_at" type="datetime-local" class="form-control"
                                value="{{ old('published_at', $blog->published_at?->timezone(config('app.timezone', 'Europe/London'))->format('Y-m-d\TH:i')) }}">
                            <div class="form-text">Set a future time to schedule publication. Times use UK local time (GMT/BST).</div>
                        </div>
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_featured" value="0">
                            <input id="is_featured" name="is_featured" value="1" type="checkbox" class="form-check-input"
                                @checked(old('is_featured', $blog->is_featured))>
                            <label for="is_featured" class="form-check-label fw-bold">Feature on blog page</label>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white p-3"><h2 class="h5 mb-0">Organisation</h2></div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label for="category" class="form-label fw-bold">Category</label>
                            <input id="category" name="category" class="form-control" maxlength="120"
                                value="{{ old('category', $blog->category) }}" list="blog-categories" required>
                            <datalist id="blog-categories"><option value="Insights"><option value="Trademarks"><option value="IP Guides"><option value="Business"><option value="News"></datalist>
                        </div>
                        <div class="mb-3">
                            <label for="tags" class="form-label fw-bold">Tags</label>
                            <input id="tags" name="tags" class="form-control" maxlength="1000"
                                value="{{ old('tags', implode(', ', $blog->tags ?? [])) }}" placeholder="trademarks, filing, startups">
                            <div class="form-text">Separate tags with commas.</div>
                        </div>
                        <div>
                            <label for="author_name" class="form-label fw-bold">Author</label>
                            <input id="author_name" name="author_name" class="form-control" maxlength="120"
                                value="{{ old('author_name', $blog->author_name) }}" required>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white p-3"><h2 class="h5 mb-0">Featured image</h2></div>
                    <div class="card-body p-4">
                        <input id="featured_image" name="featured_image" type="file" class="form-control mb-3" accept=".jpg,.jpeg,.png,.webp">
                        @error('featured_image') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <label for="featured_image_alt" class="form-label fw-bold">Image alt text</label>
                        <input id="featured_image_alt" name="featured_image_alt" class="form-control" maxlength="255"
                            value="{{ old('featured_image_alt', $blog->featured_image_alt) }}" placeholder="Describe the image for accessibility">
                        @if ($blog->featured_image_path)
                            <img class="img-fluid rounded mt-3" src="{{ route('storage.public.view', ['path' => $blog->featured_image_path]) }}" alt="">
                            <label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_featured_image" value="1"> Remove image</label>
                        @endif
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2" style="background:#2A9D8F;border:0;">{{ $submitLabel }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
    .blog-editor-form .blog-form-shell {
        width: 100%;
        background: #fff;
        border-radius: 14px;
    }

    .blog-editor-form .blog-form-shell:hover,
    .blog-editor-form .blog-form-grid > [class*="col-"] > .card:hover {
        transform: none;
    }

    .blog-editor-form .blog-form-shell > .card-body {
        padding: clamp(22px, 3vw, 38px) !important;
    }

    .blog-editor-form .blog-form-grid {
        display: block;
        margin: 0;
    }

    .blog-editor-form .blog-form-grid > [class*="col-"] {
        width: 100%;
        padding: 0;
    }

    .blog-editor-form .blog-form-grid > [class*="col-"] > .card {
        margin-bottom: 1.5rem !important;
        border: 0 !important;
        border-radius: 0;
        box-shadow: none !important;
    }

    .blog-editor-form .blog-form-grid > [class*="col-"] > .card > .card-header {
        display: none;
    }

    .blog-editor-form .blog-form-grid > [class*="col-"] > .card > .card-body {
        padding: 0 !important;
    }

    .blog-editor-form .blog-form-grid > [class*="col-"] > .card + .card {
        padding-top: 1.5rem;
        border-top: 1px solid #edf0f4 !important;
    }

    .blog-editor-form .blog-form-grid > .col-xl-4 {
        padding-top: 1.5rem;
        border-top: 1px solid #edf0f4;
    }

    .blog-editor-form .blog-form-grid > .col-xl-4 > .card:last-of-type {
        margin-bottom: 1.5rem !important;
    }

    .ck-editor__editable_inline {
        min-height: 420px;
    }

    .ck.ck-editor__main > .ck-editor__editable {
        border-color: #e0e4eb;
    }

    .ck.ck-editor__editable.ck-focused:not(.ck-editor__nested-editable) {
        border-color: #2A9D8F;
        box-shadow: 0 0 0 .2rem rgba(42, 157, 143, .12);
    }
</style>
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script>
    const blogEditorForm = document.getElementById('blogEditorForm');
    const articleContentField = document.getElementById('content');
    let articleEditor = null;

    if (window.ClassicEditor && articleContentField) {
        ClassicEditor
        .create(articleContentField, {
            toolbar: {
                items: [
                    'heading', '|',
                    'bold', 'italic', 'link', '|',
                    'bulletedList', 'numberedList', 'blockQuote', 'insertTable', '|',
                    'undo', 'redo'
                ],
                shouldNotGroupWhenFull: true
            }
        })
        .then(editor => {
            articleEditor = editor;
        })
        .catch(error => console.error('CKEditor could not be loaded:', error));
    }

    blogEditorForm?.addEventListener('submit', () => {
        if (articleEditor && articleContentField) {
            articleContentField.value = articleEditor.getData();
        }
    });

    document.querySelectorAll('[data-count-target]').forEach(field => {
        const target = document.getElementById(field.dataset.countTarget);
        const update = () => target.textContent = field.value.length;
        field.addEventListener('input', update);
        update();
    });
</script>
