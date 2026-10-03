@extends('layouts.app')

@section('content')
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        {{-- <h3>Upload Documents</h3>
                        <small>Step 4: Document Submission</small> --}}
                    </div>
                    <div class="card-body p-5">
                        <div class="alert alert-warning">
                            <i class="fas fa-file-upload"></i>
                            <strong>Upload Required Documents:</strong> All documents must be in PDF, JPG, or PNG format
                            (Max 5MB each).
                        </div>

                        <form action="{{ route('documents.store', $application->id) }}" method="POST"
                            enctype="multipart/form-data" id="documentForm">
                            @csrf

                            <div class="table-responsive mb-4">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Document Type</th>
                                            <th>Upload</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($documentTypes as $key => $label)
                                            @php
                                                $uploaded = $application
                                                    ->documents()
                                                    ->where('document_type', $key)
                                                    ->first();
                                            @endphp
                                            <tr>
                                                <td>
                                                    <strong>{{ $label }}</strong>
                                                    <br>
                                                    <small class="text-muted">PDF, JPG, PNG (Max 5MB)</small>
                                                </td>
                                                <td>
                                                    @if ($uploaded)
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-success text-white">
                                                                <i class="fas fa-check"></i> {{ $uploaded->file_name }}
                                                            </span>
                                                            <input type="file" class="form-control"
                                                                name="{{ $key }}" accept=".pdf,.jpg,.jpeg,.png">
                                                        </div>
                                                        <small class="text-muted d-block mt-1">Click to replace uploaded
                                                            file</small>
                                                    @else
                                                        <input type="file"
                                                            class="form-control @error($key) is-invalid @enderror"
                                                            name="{{ $key }}" accept=".pdf,.jpg,.jpeg,.png"
                                                            required>
                                                        @error($key)
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($uploaded)
                                                        <span
                                                            class="badge bg-{{ $uploaded->status === 'verified' ? 'success' : 'warning' }}">
                                                            {{ ucfirst($uploaded->status) }}
                                                        </span>
                                                    @else
                                                        <span class="badge bg-secondary">Pending</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Additional Information -->
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> Document Requirements</h6>
                                <ul class="mb-0">
                                    @if ($application->entity_type === 'individual')
                                        <li><strong>Identity document:</strong> Passport or driving licence</li>
                                        <li><strong>Address Proof:</strong> Utility bill, passport, or government ID</li>
                                        <li><strong>Affidavit:</strong> Auto-generated document - upload after generation</li>
                                        <li><strong>Power of Attorney:</strong> Auto-generated document - upload after generation</li>
                                    @else
                                        <li><strong>Certificate of Incorporation:</strong> Companies House document</li>
                                        <li><strong>Registered Office Address:</strong> Recent supporting document</li>
                                        <li><strong>Authorized Signatory ID:</strong> Passport or driving licence</li>
                                        <li><strong>Affidavit:</strong> Auto-generated document - upload after generation</li>
                                        <li><strong>Power of Attorney:</strong> Auto-generated document - upload after generation</li>
                                    @endif
                                </ul>
                            </div>

                            <!-- Declaration -->
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h6 class="card-title">Declaration</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="declaration" required>
                                        <label class="form-check-label" for="declaration">
                                            I declare that all the information provided is true and correct. I have uploaded
                                            all required documents in the specified format.
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <a href="{{ route('trademark.detailed-form', $application->id) }}"
                                        class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-arrow-left"></i> Back
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <button type="submit" class="btn btn-success w-100 btn-lg">
                                        <i class="fas fa-check"></i> Submit for Approval
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('documentForm').addEventListener('submit', function(e) {
            var declaration = document.getElementById('declaration').checked;
            if (!declaration) {
                e.preventDefault();
                alert('Please accept the declaration before submitting.');
            }
        });
    </script>
@endsection
