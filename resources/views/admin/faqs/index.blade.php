@extends('layouts.app')

@section('content')
    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1" style="color:#1D3557;">Frequently Asked Questions</h1>
                <p class="text-muted mb-0">Create, order, publish, and update the accordion FAQs shown on the UK homepage.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('landing') }}#faqs" target="_blank" class="btn btn-outline-secondary btn-sm">View Homepage FAQs</a>
                <a href="{{ route('admin.faqs.create') }}" class="btn btn-primary btn-sm" style="background:#2A9D8F;border:0;">
                    <i class="fas fa-plus"></i> Add FAQ
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.faqs.index') }}" class="d-flex gap-2 flex-wrap">
                    <input name="search" class="form-control form-control-sm" value="{{ request('search') }}"
                        placeholder="Search questions, answers, or categories">
                    <select name="status" class="form-select form-select-sm" style="max-width:180px">
                        <option value="">All statuses</option>
                        <x-admin-status-option value="published" label="Published" :selected="request('status') === 'published'" />
                        <x-admin-status-option value="draft" label="Draft" :selected="request('status') === 'draft'" />
                    </select>
                    <button class="btn btn-primary btn-sm" style="background:#2A9D8F;border:0;">Search &amp; filter</button>
                    @if (request()->hasAny(['search', 'status'])) <a href="{{ route('admin.faqs.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a> @endif
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                @if ($faqs->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle admin-list-table">
                            <thead>
                                <tr>
                                    <th style="width:85px;">Order</th>
                                    <th>Question</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($faqs as $faq)
                                    <tr>
                                        <td>{{ $faq->sort_order }}</td>
                                        <td>
                                            <strong style="color:#1D3557;">{{ $faq->question }}</strong>
                                            <div class="small text-muted">{{ Str::limit($faq->answer, 95) }}</div>
                                        </td>
                                        <td><span class="badge text-bg-light border">{{ $faq->category }}</span></td>
                                        <td><x-admin-status :status="$faq->is_active ? 'Published' : 'Draft'" /></td>
                                        <td><x-admin-date-time :value="$faq->updated_at" /></td>
                                        <td>
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="{{ route('admin.faqs.edit', $faq) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}"
                                                    data-swal-confirm data-swal-title="Delete this FAQ?"
                                                    data-swal-text="This removes it from the public FAQ page."
                                                    data-swal-icon="warning" data-swal-confirm-text="Yes, delete">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $faqs->links() }}
                @else
                    <div class="text-center py-5">
                        <h5>No FAQs found</h5>
                        <p class="text-muted">Create an FAQ to publish helpful answers on the website.</p>
                        <a href="{{ route('admin.faqs.create') }}" class="btn btn-primary" style="background:#2A9D8F;border:0;">Add FAQ</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
