@extends('layouts.app')

@section('content')
    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2>📋 My Documents</h2>
                <p class="text-muted">Download and manage your trademark documents</p>
            </div>
        </div>

        <!-- Status Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <h6 class="text-muted">My Applications</h6>
                        <h3>{{ $userApplicationCount }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <h6 class="text-muted">Approved</h6>
                        <h3>{{ $approvedCount }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <h6 class="text-muted">Total Documents</h6>
                        <h3>{{ $totalDocuments }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <h6 class="text-muted">Signed & Verified</h6>
                        <h3>{{ $verifiedCount }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applications with Documents -->
        @forelse($applications as $application)
            <div class="card mb-4 shadow-sm" id="application-{{ $application->id }}">
                <div class="card-header bg-gradient"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">
                                {{ $application->brand_name }}
                                <span
                                    class="badge
                                @if ($application->status === 'approved') bg-success
                                @elseif($application->status === 'filed') bg-info
                                @elseif($application->status === 'registered') bg-primary
                                @else bg-warning @endif">
                                    {{ ucfirst($application->status) }}
                                </span>
                            </h5>
                            <small>Trademark Application ID: #{{ $application->id }} • Application No:
                                {{ $application->application_number ?? 'Awaiting assignment' }} •
                                {{ $application->created_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}</small>
                        </div>
                        <div class="col-md-6 text-end">
                            <span class="badge bg-light text-dark">{{ $application->documents->count() }} Documents</span>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Application Info -->
                    <div class="row mb-4 pb-3 border-bottom">
                        <div class="col-md-3">
                            <p class="mb-1"><strong>📝 Applicant:</strong></p>
                            <p class="text-muted">{{ $application->applicant_name }}</p>
                        </div>
                        <div class="col-md-3">
                            <p class="mb-1"><strong>Trademark Application ID:</strong></p>
                            <p class="text-muted">#{{ $application->id }}</p>
                        </div>
                        <div class="col-md-3">
                            <p class="mb-1"><strong>🏢 Industry/Class:</strong></p>
                            <p class="text-muted">{{ $application->industry }}</p>
                        </div>
                        <div class="col-md-3">
                            <p class="mb-1"><strong>📱 Status:</strong></p>
                            <p class="text-muted">
                                @switch($application->status)
                                    @case('pending_admin')
                                        <span class="badge bg-warning">⏳ Pending Review</span>
                                    @break

                                    @case('approved')
                                        <span class="badge bg-success">✅ Approved</span>
                                    @break

                                    @case('filed')
                                        <span class="badge bg-info">📁 Filed</span>
                                    @break

                                    @case('registered')
                                        <span class="badge bg-primary">✔️ Registered</span>
                                    @break

                                    @default
                                        <span class="badge bg-secondary">{{ ucfirst($application->status) }}</span>
                                @endswitch
                            </p>
                        </div>
                    </div>

                    @if ($application->current_status === \App\Support\TrademarkWorkflow::ONBOARDING_PENDING)
                        <div class="alert alert-info mb-4">
                            <strong>Onboarding Actions</strong>
                            <div class="mt-2">1. Upload the signed Engagement Letter.</div>
                            <div>2. Upload the 50% payment invoice copy.</div>
                            <div>3. Upload the signed POA.</div>
                            <div>4. Submit your onboarding digital signature from the application Action Center. The system will auto-sign the engagement letter and POA PDFs for you.</div>
                        </div>
                    @endif

                    <!-- Documents Section -->
                    @if ($application->documents->count() > 0)
                        @php
                            // Filter: only show approved or user-uploaded documents (exclude archived and generated)
                            $visibleDocs = $application->documents->whereIn('status', ['approved', 'uploaded', 'signed', 'verified']);
                            $generatedDocs = $application->documents->where('status', 'generated');
                        @endphp

                        @if ($visibleDocs->count() > 0)
                            <h6 class="mb-3">📄 Available Documents</h6>
                            <div class="row mb-4">
                                @foreach ($visibleDocs as $doc)
                                    <div class="col-md-6 mb-3">
                                        <div class="card border-left-{{ $doc->document_type === 'affidavit' ? 'primary' : 'info' }}">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h6 class="mb-0">
                                            @if ($doc->document_type === 'engagement_letter')
                                                🤝 Engagement Letter
                                            @elseif ($doc->document_type === 'tm_intake_form')
                                                📝 TM Intake Form
                                            @elseif (str_contains(strtolower($doc->document_type), 'affidavit'))
                                                📋 Affidavit
                                            @elseif (str_contains(strtolower($doc->document_type), 'poa'))
                                                ✍️ Power of Attorney
                                            @elseif ($doc->document_type === 'invoice')
                                                💳 Invoice
                                            @elseif ($doc->document_type === 'search_report')
                                                🔎 Search Report
                                            @elseif ($doc->document_type === 'draft_pdf')
                                                🧾 Draft PDF
                                            @elseif ($doc->document_type === 'draft_pdf (Signed)')
                                                ✅ Signed Draft PDF
                                            @else
                                                📄 {{ ucfirst($doc->document_type) }}
                                            @endif
                                        </h6>
                                                        <small class="text-muted">{{ $doc->file_name }}</small>
                                                    </div>

                                                    <span class="badge
                                                @if ($doc->status === 'approved') bg-success
                                                @elseif($doc->status === 'uploaded') bg-info
                                                @elseif($doc->status === 'verified') bg-primary
                                                @else bg-warning @endif">
                                                        @if($doc->status === 'approved')
                                                            ✅ Ready
                                                        @elseif($doc->status === 'uploaded')
                                                            📤 Uploaded
                                                        @elseif($doc->status === 'verified')
                                                            ✔️ Verified
                                                        @else
                                                            {{ ucfirst($doc->status) }}
                                                        @endif
                                                    </span>
                                                </div>

                                                <div class="btn-group" role="group" style="width: 100%;">
                                                    <!-- View Button -->
                                                    <a href="{{ route('user.document.view', $doc->id) }}" target="_blank"
                                                        class="btn btn-sm btn-outline-primary">
                                                        👁️ View
                                                    </a>

                                                    <!-- Download Button -->
                                                    <a href="{{ route('user.document.download', $doc->id) }}"
                                                        class="btn btn-sm btn-outline-success">
                                                        ⬇️ Download
                                                    </a>

                                                </div>

                                                @if ($doc->verified_at)
                                                    <div class="alert alert-success alert-sm mt-2 mb-0" role="alert">
                                                        ✔️ Verified on {{ $doc->verified_at->timezone(config('app.timezone', 'Europe/London'))->format('d M Y') }}
                                                        @if ($doc->verification_notes)
                                                            <br><small>{{ $doc->verification_notes }}</small>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Upload Modal removed - using single reusable modal below -->

                                @endforeach
                            </div>
                        @endif

                        @if ($generatedDocs->count() > 0 && $application->status === 'pending_admin')
                            <div class="alert alert-info" role="alert">
                                <strong>⏳ Waiting for Admin Approval</strong>
                                <p class="mb-0">Your application documents are being reviewed by admin. Once approved, you'll be able to download and upload signed versions here.</p>
                            </div>
                        @endif

                    @else
                        @if ($application->status === 'pending_admin')
                            <div class="alert alert-info" role="alert">
                                <strong>📋 Awaiting Admin Review</strong>
                                <p class="mb-0">Your application is under review. Once approved, Affidavit and Power of Attorney documents will appear here for download.</p>
                            </div>
                        @elseif ($application->status === 'pending_documents')
                            <div class="alert alert-warning" role="alert">
                                <strong>📤 Upload Documents</strong>
                                <p class="mb-0">Please upload the required documents to proceed with your application.</p>
                            </div>
                        @else
                            <div class="alert alert-info" role="alert">
                                <p class="mb-0">No documents available yet.</p>
                            </div>
                        @endif
                    @endif

                </div>
            </div>

            @if ($loop->first)
                <!-- Single Reusable Upload Modal -->
                <div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-light">
                                <h5 class="modal-title" id="uploadModalLabel">📤 Upload Signed Document</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form id="uploadFormReusable" action="{{ route('user.document.upload-signed') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="modal-body">
                                    <input type="hidden" id="docId" name="document_id">
                                    <input type="hidden" id="appId" name="application_id">
                                    
                                    <div class="mb-3">
                                        <label for="signedDoc" class="form-label"><strong>Select Signed Document *</strong></label>
                                        <input type="file" id="signedDoc" name="signed_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                        <small class="d-block text-muted mt-1">📄 Accepted: PDF, JPG, PNG (Max 10MB)</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes (Optional)</label>
                                        <textarea id="notes" name="signature_notes" class="form-control" rows="2" placeholder="Add any notes about your signature..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Cancel</button>
                                    <button type="submit" class="btn btn-success">✅ Upload & Submit</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            @endif

            @empty
                <div class="alert alert-info" role="alert">
                    <h4>📋 No Applications Found</h4>
                    <p>You haven't submitted any trademark applications yet. <a href="{{ route('home') }}">Start an
                            application now →</a></p>
                </div>
            @endforelse

        </div>

        <!-- Styles -->
        <style>
            .card {
                border: none;
                transition: transform 0.2s, box-shadow 0.2s;
            }

            .card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
            }

            .btn-group .btn {
                flex: 1;
            }

            .border-left-primary {
                border-left: 4px solid #667eea !important;
            }

            .border-left-info {
                border-left: 4px solid #17a2b8 !important;
            }

            .alert-sm {
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
            }

            .bg-gradient {
                background-size: cover;
            }
        </style>

        <!-- JavaScript for Upload Modal -->
        <script>
            let uploadModalInstance; // Store modal instance globally
            const uploadForm = document.getElementById('uploadFormReusable');

            // Handle upload modal opening with dynamic population
            document.querySelectorAll('.openUploadModal').forEach(button => {
                button.addEventListener('click', function() {
                    const docId = this.getAttribute('data-doc-id');
                    const appId = this.getAttribute('data-app-id');
                    
                    // Populate hidden inputs with current document data
                    document.getElementById('docId').value = docId;
                    document.getElementById('appId').value = appId;
                    
                    // Reset file input and notes
                    document.getElementById('signedDoc').value = '';
                    document.getElementById('notes').value = '';
                    
                    // Show the modal
                    uploadModalInstance = new bootstrap.Modal(document.getElementById('uploadModal'));
                    uploadModalInstance.show();
                });
            });

            // Handle form submission
            if (uploadForm) {
                uploadForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                
                    const docId = document.getElementById('docId').value;
                    const appId = document.getElementById('appId').value;
                    const fileInput = document.getElementById('signedDoc');
                    const notesInput = document.getElementById('notes');
                
                    if (!fileInput.files[0]) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'No File Selected',
                            text: 'Please select a document to upload'
                        });
                        return;
                    }

                    const confirmation = await Swal.fire({
                        icon: 'question',
                        title: 'Upload signed document?',
                        text: 'Please confirm that the selected file is the correct signed document.',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, upload',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#2A9D8F',
                        cancelButtonColor: '#6c757d',
                        reverseButtons: true,
                        focusCancel: true,
                    });

                    if (!confirmation.isConfirmed) {
                        return;
                    }

                    const formData = new FormData();
                    formData.append('document_id', docId);
                    formData.append('application_id', appId);
                    formData.append('signed_document', fileInput.files[0]);
                    formData.append('signature_notes', notesInput.value);
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                    // Show loading state
                    const submitBtn = this.querySelector('button[type="submit"]');
                    window.LegalBruzButtonLoading?.set(submitBtn, 'Uploading...');

                    try {
                        const response = await fetch('{{ route('user.document.upload-signed') }}', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        const data = await response.json();

                        if (data.success) {
                            // Close modal first
                            if (uploadModalInstance) {
                                uploadModalInstance.hide();
                            }
                        
                            // Reload page immediately
                            setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Failed to upload document'
                            });
                            window.LegalBruzButtonLoading?.reset(submitBtn);
                        }
                    } catch (error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to upload document: ' + error.message
                        });
                        window.LegalBruzButtonLoading?.reset(submitBtn);
                    }
                });
            }

        </script>
    @endsection
