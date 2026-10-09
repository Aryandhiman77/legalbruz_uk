@extends('layouts.app')

@section('content')
    @php
        $imageUrl = fn (string $path) => str_starts_with($path, 'cms/')
            ? route('storage.public.view', ['path' => $path])
            : asset($path);
    @endphp

    <div class="about-cms-page">
        <header class="about-cms-head">
            <div><span>About CMS</span><h1>Edit About Us</h1><p>Edit every visible About page section and replace its images.</p></div>
            <a href="{{ route('about') }}" class="btn btn-outline-light" target="_blank" rel="noopener">View page</a>
        </header>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.cms-pages.update', \App\Models\CmsPage::ABOUT) }}" enctype="multipart/form-data" class="about-cms-form" data-about-form data-preview-url="{{ route('admin.cms-pages.about.preview') }}">
            @csrf
            @method('PUT')

            @php
                $sections = [
                    'Hero section' => [
                        ['hero_eyebrow', 'Eyebrow'], ['hero_title', 'Main heading'], ['hero_title_accent', 'Highlighted heading'],
                        ['hero_copy', 'Introduction', 'rich'], ['hero_principle_label', 'Principle label'],
                        ['hero_principle_text', 'Principle statement'], ['hero_note_line_1', 'Image note line 1'], ['hero_note_line_2', 'Image note line 2'],
                    ],
                    'Story section' => [
                        ['story_kicker', 'Section label'], ['story_title', 'Heading'], ['story_copy', 'Story content', 'rich'],
                    ],
                    'Mission and vision' => [
                        ['mission_title', 'Mission heading'], ['mission_copy', 'Mission content', 'rich'],
                        ['vision_title', 'Vision heading'], ['vision_copy', 'Vision content', 'rich'],
                    ],
                    'Founder section' => [
                        ['founder_kicker', 'Section label'], ['founder_title', 'Heading'],
                        ['founder_name', 'Founder name'], ['founder_role', 'Founder role'],
                        ['founder_copy', 'Founder biography', 'rich'], ['focus_heading', 'Focus heading'],
                        ['focus_items', 'Focus items (one per line)', 'multiline'],
                    ],
                    'Trust section' => [
                        ['trust_kicker', 'Section label'], ['trust_title', 'Heading'],
                        ['trust_1_title', 'Card 1 title'], ['trust_1_copy', 'Card 1 description'],
                        ['trust_2_title', 'Card 2 title'], ['trust_2_copy', 'Card 2 description'],
                        ['trust_3_title', 'Card 3 title'], ['trust_3_copy', 'Card 3 description'],
                        ['trust_4_title', 'Card 4 title'], ['trust_4_copy', 'Card 4 description'],
                    ],
                    'Call to action' => [
                        ['cta_kicker', 'Section label'], ['cta_title', 'Heading'],
                        ['cta_copy', 'Description', 'rich'], ['cta_label', 'Button label'],
                    ],
                ];
            @endphp

            @foreach ($sections as $section => $fields)
                <section class="about-cms-card">
                    <h2>{{ $section }}</h2>
                    <div class="about-cms-grid">
                        @foreach ($fields as $field)
                            @php([$name, $label, $type] = [$field[0], $field[1], $field[2] ?? 'text'])
                            <div class="about-cms-field {{ in_array($type, ['rich', 'multiline'], true) ? 'is-wide' : '' }}">
                                <label for="{{ $name }}">{{ $label }}</label>
                                @if ($type === 'rich')
                                    <textarea id="{{ $name }}" name="{{ $name }}" class="form-control rich-editor @error($name) is-invalid @enderror" rows="6" required>{{ old($name, $about[$name]) }}</textarea>
                                @elseif ($type === 'multiline')
                                    <textarea id="{{ $name }}" name="{{ $name }}" class="form-control @error($name) is-invalid @enderror" rows="7" required>{{ old($name, $about[$name]) }}</textarea>
                                @else
                                    <input id="{{ $name }}" name="{{ $name }}" class="form-control @error($name) is-invalid @enderror" value="{{ old($name, $about[$name]) }}" required>
                                @endif
                                @error($name) <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <section class="about-cms-card">
                <h2>Images</h2>
                <div class="about-image-grid">
                    @foreach ([['hero', 'Hero brand image'], ['founder', 'Founder photograph']] as [$key, $label])
                        <div class="about-image-field">
                            <img src="{{ $imageUrl($about[$key.'_image']) }}" alt="Current {{ strtolower($label) }}">
                            <div><label for="{{ $key }}_image_upload">{{ $label }}</label><input id="{{ $key }}_image_upload" name="{{ $key }}_image_upload" type="file" class="form-control" accept=".jpg,.jpeg,.png,.webp"><small>JPG, PNG or WebP. Maximum 5 MB.</small></div>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="about-cms-actions">
                <a href="{{ route('admin.dashboard') }}#website-cms" class="btn btn-outline-secondary">Back to Website CMS</a>
                <button class="btn btn-outline-primary" type="button" data-about-preview>Preview Page</button>
                <button class="btn btn-primary" type="submit">Save About Page</button>
            </div>
        </form>
    </div>

    <div class="about-preview-modal" data-about-preview-modal hidden>
        <div class="about-preview-backdrop" data-about-preview-close></div>
        <section class="about-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="about-preview-title">
            <header class="about-preview-head">
                <div><span>Unsaved preview</span><h2 id="about-preview-title">About Page Preview</h2></div>
                <button type="button" data-about-preview-close aria-label="Close preview"><i class="bi bi-x-lg"></i></button>
            </header>
            <div class="about-preview-loading" data-about-preview-loading><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Preparing preview…</div>
            <div class="about-preview-error" data-about-preview-error hidden></div>
            <iframe title="About page preview" data-about-preview-frame sandbox="allow-same-origin" hidden></iframe>
        </section>
    </div>

    <style>
        .about-cms-page{max-width:1180px;margin:0 auto 40px;padding:0 18px;color:#172b46}.about-cms-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:18px;padding:28px;border-radius:18px;background:linear-gradient(135deg,#071f48,#0d6b69);color:#fff}.about-cms-head span{color:#8de8dc;font-size:.68rem;font-weight:900;letter-spacing:.13em;text-transform:uppercase}.about-cms-head h1{margin:7px 0;color:#fff}.about-cms-head p{margin:0;color:rgba(255,255,255,.78)}.about-cms-form{display:grid;gap:16px}.about-cms-card{padding:22px;border:1px solid #dfe8f4;border-radius:16px;background:#fff;box-shadow:0 12px 28px rgba(8,36,90,.06)}.about-cms-card h2{margin:0 0 18px;color:#102a4c;font-size:1.05rem}.about-cms-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.about-cms-field.is-wide{grid-column:1/-1}.about-cms-field label,.about-image-field label{display:block;margin-bottom:7px;font-weight:800}.about-image-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.about-image-field{display:grid;grid-template-columns:130px 1fr;gap:16px;align-items:center;padding:14px;border:1px solid #e2e9f1;border-radius:13px}.about-image-field img{width:130px;height:120px;object-fit:contain;border-radius:10px;background:#f3f7fa}.about-image-field small{display:block;margin-top:7px;color:#718096}.about-cms-actions{position:sticky;bottom:12px;display:flex;justify-content:flex-end;gap:10px;padding:15px;border:1px solid #dfe8f4;border-radius:14px;background:rgba(255,255,255,.96);box-shadow:0 14px 34px rgba(7,31,72,.12);z-index:5}.ck-editor__editable_inline{min-height:150px}.about-preview-modal{position:fixed;inset:0;z-index:10000;display:grid;place-items:center;padding:22px}.about-preview-modal[hidden]{display:none}.about-preview-backdrop{position:absolute;inset:0;background:rgba(3,19,44,.72);backdrop-filter:blur(5px)}.about-preview-dialog{position:relative;display:grid;grid-template-rows:auto 1fr;width:min(1400px,96vw);height:min(900px,92vh);overflow:hidden;border-radius:18px;background:#fff;box-shadow:0 35px 90px rgba(0,0,0,.35)}.about-preview-head{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:15px 20px;background:linear-gradient(135deg,#071f48,#0d6b69);color:#fff}.about-preview-head span{color:#8de8dc;font-size:.65rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase}.about-preview-head h2{margin:3px 0 0;color:#fff;font-size:1.1rem}.about-preview-head button{display:grid;place-items:center;width:38px;height:38px;border:1px solid rgba(255,255,255,.24);border-radius:10px;background:rgba(255,255,255,.1);color:#fff}.about-preview-loading,.about-preview-error{align-self:center;justify-self:center;padding:24px;color:#52627a}.about-preview-loading{display:flex;align-items:center;gap:10px}.about-preview-error{max-width:620px;border-radius:12px;background:#fff1f1;color:#a33;text-align:center}.about-preview-dialog iframe{width:100%;height:100%;border:0;background:#fff}@media(max-width:760px){.about-cms-head{align-items:flex-start;flex-direction:column}.about-cms-grid,.about-image-grid{grid-template-columns:1fr}.about-image-field{grid-template-columns:1fr}.about-cms-actions{position:static;flex-wrap:wrap}.about-preview-modal{padding:8px}.about-preview-dialog{width:100%;height:96vh;border-radius:12px}}
    </style>

    <script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
    <script>
        const aboutEditors = [];
        document.querySelectorAll('.rich-editor').forEach(field => {
            ClassicEditor.create(field, {
                toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'undo', 'redo'],
            }).then(editor => aboutEditors.push(editor)).catch(error => console.error(error));
        });

        (() => {
            const form = document.querySelector('[data-about-form]');
            const previewButton = document.querySelector('[data-about-preview]');
            const modal = document.querySelector('[data-about-preview-modal]');
            const frame = modal?.querySelector('[data-about-preview-frame]');
            const loading = modal?.querySelector('[data-about-preview-loading]');
            const errorBox = modal?.querySelector('[data-about-preview-error]');
            let previousFocus = null;

            if (!form || !previewButton || !modal || !frame || !loading || !errorBox) return;

            const closePreview = () => {
                modal.hidden = true;
                document.body.style.overflow = '';
                frame.removeAttribute('srcdoc');
                frame.hidden = true;
                previousFocus?.focus();
            };

            modal.querySelectorAll('[data-about-preview-close]').forEach(button => button.addEventListener('click', closePreview));
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && !modal.hidden) closePreview();
            });

            previewButton.addEventListener('click', async () => {
                aboutEditors.forEach(editor => {
                    editor.sourceElement.value = editor.getData();
                });

                previousFocus = document.activeElement;
                modal.hidden = false;
                document.body.style.overflow = 'hidden';
                loading.hidden = false;
                errorBox.hidden = true;
                frame.hidden = true;
                previewButton.disabled = true;

                try {
                    const response = await fetch(form.dataset.previewUrl, {
                        method: 'POST',
                        body: new FormData(form),
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html, application/json' },
                    });

                    if (!response.ok) {
                        let message = 'Preview could not be prepared. Please check the form fields.';
                        const contentType = response.headers.get('content-type') || '';
                        if (contentType.includes('application/json')) {
                            const payload = await response.json();
                            const firstError = Object.values(payload.errors || {}).flat()[0];
                            message = firstError || payload.message || message;
                        }
                        throw new Error(message);
                    }

                    frame.srcdoc = await response.text();
                    frame.hidden = false;
                } catch (error) {
                    errorBox.textContent = error.message || 'Preview could not be prepared.';
                    errorBox.hidden = false;
                } finally {
                    loading.hidden = true;
                    previewButton.disabled = false;
                }
            });
        })();
    </script>
@endsection
