@extends('layouts.app')

@section('content')
    @php
        $title = old('title', $page->title ?? $fallback['title']);
        $content = old('content', $page->content ?? $fallback['content']);
    @endphp

    <div class="cms-edit-page">
        <div class="cms-edit-head">
            <div>
                <span>CMS editor</span>
                <h1>{{ $title }}</h1>
                <p>Changes are reflected immediately wherever this content is shown.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}#website-cms" class="btn btn-outline-light">Back to Website CMS</a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.cms-pages.update', $fallback['key']) }}" class="cms-edit-card">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label fw-bold" for="title">Title</label>
                <input id="title" class="form-control @error('title') is-invalid @enderror" name="title" value="{{ $title }}" maxlength="180" required>
                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold" for="content">Content</label>
                <textarea id="content" class="form-control @error('content') is-invalid @enderror" name="content" rows="18" required>{{ $content }}</textarea>
                @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="cms-edit-actions">
                <a href="{{ route('admin.dashboard') }}#website-cms" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Content</button>
            </div>
        </form>
    </div>

    <style>
        .cms-edit-page{max-width:1160px;margin:0 auto 36px;padding:0 18px;color:#172b46}
        .cms-edit-head{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:18px;padding:28px;border-radius:18px;background:linear-gradient(135deg,#071f48,#0d6b69);color:#fff;box-shadow:0 18px 40px rgba(7,31,72,.13)}
        .cms-edit-head span{color:#8de8dc;font-size:.68rem;font-weight:900;letter-spacing:.13em;text-transform:uppercase}
        .cms-edit-head h1{margin:7px 0;color:#fff;font-size:clamp(1.45rem,3vw,2rem);font-weight:950}
        .cms-edit-head p{margin:0;color:rgba(255,255,255,.78)}
        .cms-edit-card{padding:22px;border:1px solid #dfe8f4;border-radius:16px;background:#fff;box-shadow:0 12px 28px rgba(8,36,90,.06)}
        .cms-edit-actions{display:flex;justify-content:flex-end;gap:10px}
        .ck-editor__editable_inline{min-height:520px}
        @media(max-width:760px){.cms-edit-head{align-items:flex-start;flex-direction:column}.cms-edit-actions{display:grid}}
    </style>

    <script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
    <script>
        ClassicEditor
            .create(document.querySelector('#content'), {
                toolbar: [
                    'heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList',
                    '|', 'blockQuote', 'insertTable', 'undo', 'redo'
                ],
            })
            .catch(error => console.error(error));
    </script>
@endsection
