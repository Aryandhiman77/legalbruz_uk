@extends('layouts.app')

@section('content')
    <div class="container py-4" style="max-width:1000px;">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
            <div>
                <span class="text-uppercase fw-bold" style="color:#159485;font-size:.7rem;letter-spacing:.12em;">Trademark Search Report</span>
                <h1 class="h3 mt-2 mb-1" style="color:#1D3557;">{{ $reportRequest->brand_name }}</h1>
                <p class="text-muted mb-0">Requested {{ $reportRequest->created_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y, h:i A T') }}</p>
            </div>
            <a href="{{ route('admin.trademark-search-reports.index') }}" class="btn btn-outline-secondary btn-sm align-self-start">Back to Search Reports</a>
        </div>

        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

        <div class="row g-3 mb-3">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
                    <h2 class="h5 mb-3">Client and brand details</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Name</dt><dd class="col-sm-8">{{ $reportRequest->name }}</dd>
                        <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><a href="mailto:{{ $reportRequest->email }}">{{ $reportRequest->email }}</a></dd>
                        <dt class="col-sm-4">Phone</dt><dd class="col-sm-8">{{ $reportRequest->phone }}</dd>
                        <dt class="col-sm-4">Brand Name</dt><dd class="col-sm-8"><strong>{{ $reportRequest->brand_name }}</strong></dd>
                        <dt class="col-sm-4">Goods/Services</dt><dd class="col-sm-8" style="white-space:pre-wrap;">{{ $reportRequest->business_activity }}</dd>
                    </dl>
                </div></div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
                    <h2 class="h5 mb-3">Payment record</h2>
                    <dl class="row mb-0">
                        <dt class="col-5">Status</dt><dd class="col-7"><x-admin-status :status="$reportRequest->payment_status" /></dd>
                        <dt class="col-5">Amount</dt><dd class="col-7">£{{ number_format((float) $reportRequest->amount, 2) }} {{ $reportRequest->currency }}</dd>
                        <dt class="col-5">Order ID</dt><dd class="col-7 text-break">{{ $reportRequest->razorpay_order_id ?: 'Not created' }}</dd>
                        <dt class="col-5">Transaction</dt><dd class="col-7 text-break">{{ $reportRequest->transaction_id ?: 'Not available' }}</dd>
                        <dt class="col-5">Paid at</dt><dd class="col-7">{{ $reportRequest->paid_at?->timezone(config('app.timezone', 'Europe/London'))->format('d M Y, h:i A T') ?: 'Not paid yet' }}</dd>
                    </dl>
                </div></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.trademark-search-reports.update', $reportRequest) }}" enctype="multipart/form-data">
                    @csrf @method('PATCH')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="report_status" class="form-label fw-bold">Report Status</label>
                            <select id="report_status" name="report_status" class="form-select" required>
                                @foreach ($reportStatuses as $value => $label)<option value="{{ $value }}" @selected(old('report_status', $reportRequest->report_status) === $value)>{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="w-100">
                                <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#addReportDocumentModal">
                                    <i class="bi bi-file-earmark-plus me-1"></i>Add Document
                                </button>
                                <div class="form-text">Add each named PDF as an independent client document.</div>
                            </div>
                        </div>
                        <div class="col-12">
                            @if ($reportRequest->documents->isNotEmpty())
                                <div class="mb-3">
                                    <h3 class="h6 mb-2">Uploaded PDF Reports ({{ $reportRequest->documents->count() }})</h3>
                                    <div class="d-grid gap-2">
                                        @foreach ($reportRequest->documents as $document)
                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 rounded" style="background:#eef8f6;">
                                                <div>
                                                    <strong><i class="bi bi-file-earmark-pdf me-1"></i>{{ $document->display_name }}</strong>
                                                    <small class="d-block text-muted">File: {{ $document->original_name }} · Uploaded {{ $document->uploaded_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y, h:i A T') }}</small>
                                                </div>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <a href="{{ route('admin.trademark-search-reports.document.download', [$reportRequest, $document]) }}" class="btn btn-sm btn-outline-primary">Download PDF</a>
                                                    <button type="submit" form="remove-report-document-{{ $document->id }}" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this PDF report? The client will no longer be able to download it.')"><i class="bi bi-trash me-1"></i>Remove</button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            <label for="admin_notes" class="form-label fw-bold">Admin Internal Notes</label>
                            <textarea id="admin_notes" name="admin_notes" class="form-control @error('admin_notes') is-invalid @enderror" rows="5" maxlength="10000" placeholder="Private review notes for the admin team...">{{ old('admin_notes', $reportRequest->admin_notes) }}</textarea>
                            <div class="form-text">Private notes—never shown to the client.</div>
                            @error('admin_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-4"><button class="btn btn-primary" style="background:#2A9D8F;border:0;">Save Report Changes</button></div>
                </form>
                @foreach ($reportRequest->documents as $document)
                    <form id="remove-report-document-{{ $document->id }}" method="POST" action="{{ route('admin.trademark-search-reports.document.destroy', [$reportRequest, $document]) }}" class="d-none">
                        @csrf
                        @method('DELETE')
                    </form>
                @endforeach
            </div>
        </div>
    </div>

    <div class="modal fade" id="addReportDocumentModal" tabindex="-1" aria-labelledby="addReportDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="{{ route('admin.trademark-search-reports.document.store', $reportRequest) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header border-0 pb-0">
                        <div>
                            <h2 class="modal-title h5 mb-1" id="addReportDocumentModalLabel">Add Document</h2>
                            <p class="text-muted small mb-0">Name and upload one client PDF.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-4">
                        <div class="mb-3">
                            <label for="document_name" class="form-label fw-bold">Document Name</label>
                            <input id="document_name" name="document_name" type="text" maxlength="150" value="{{ old('document_name') }}" class="form-control @error('document_name') is-invalid @enderror" placeholder="e.g. Trademark Search Report" required>
                            @error('document_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="document_file" class="form-label fw-bold">PDF File</label>
                            <input id="document_file" name="document_file" type="file" class="form-control @error('document_file') is-invalid @enderror" accept="application/pdf,.pdf" required>
                            <div class="form-text">Private PDF, maximum 20 MB.</div>
                            @error('document_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background:#2A9D8F;border-color:#2A9D8F;">
                            <i class="bi bi-check2-circle me-1"></i>Save Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if ($errors->has('document_name') || $errors->has('document_file'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('addReportDocumentModal')).show();
            });
        </script>
    @endif
@endsection
