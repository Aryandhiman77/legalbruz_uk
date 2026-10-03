<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1" style="color:#1D3557;">{{ $title }}</h1>
            <p class="text-muted mb-0">Published FAQs appear immediately in the UK homepage accordion and on the public FAQ page.</p>
        </div>
        <a href="{{ route('admin.faqs.index') }}" class="btn btn-outline-secondary btn-sm">Back to FAQs</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ $action }}">
        @csrf
        @if ($method !== 'POST') @method($method) @endif
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-12">
                        <label for="question" class="form-label fw-bold">Question</label>
                        <input id="question" name="question" type="text"
                            class="form-control @error('question') is-invalid @enderror"
                            value="{{ old('question', $faq->question) }}" maxlength="500" required>
                        @error('question') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label for="answer" class="form-label fw-bold">Answer</label>
                        <textarea id="answer" name="answer" rows="8"
                            class="form-control @error('answer') is-invalid @enderror"
                            maxlength="10000" required>{{ old('answer', $faq->answer) }}</textarea>
                        @error('answer') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Website section</label>
                        <input name="category" type="hidden" value="UK Trade Marks">
                        <div class="form-control bg-light">UK homepage · Trade mark FAQs</div>
                    </div>
                    <div class="col-md-3">
                        <label for="sort_order" class="form-label fw-bold">Display order</label>
                        <input id="sort_order" name="sort_order" type="number"
                            class="form-control @error('sort_order') is-invalid @enderror"
                            value="{{ old('sort_order', $faq->sort_order) }}" min="0" max="65535" required>
                        @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active"
                                name="is_active" value="1" @checked(old('is_active', $faq->is_active))>
                            <label class="form-check-label fw-bold" for="is_active">Published</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white p-4 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary" style="background:#2A9D8F;border:0;">{{ $submitLabel }}</button>
            </div>
        </div>
    </form>
</div>
