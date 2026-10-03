@php
    $fileFields = [
        'engagement_letter' => ['name' => 'engagement_letter_file', 'accept' => '.pdf'],
    ];
    $plainUploadDocuments = $plainUploadDocuments ?? [];
    $pendingUploads = session('admin_onboarding_uploads.' . $application->id, []);
@endphp

<div class="onboarding-setup-panel mt-3" data-signing-setup>
    <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h6 class="mb-1">Onboarding Documents</h6>
            <p class="small text-muted mb-0">Upload each document and drag the signature/date boxes onto the preview.</p>
        </div>
        <span class="badge bg-light text-dark border">Drag fields</span>
    </div>

    @foreach ($placementDocuments as $documentType => $documentLabel)
        @php
            $fileField = $fileFields[$documentType];
            $required = $documentType !== 'other_document';
            $signatureRequired = in_array($documentType, $signatureRequiredDocuments, true);
            $existingDocument = $application->documents->where('document_type', $documentType)->where('status', 'approved')->sortByDesc('id')->first();
            $pendingDocument = $pendingUploads[$documentType] ?? null;
            $hasPendingDocument = is_array($pendingDocument) && !empty($pendingDocument['path']);
            $hasUsableDocument = $existingDocument || $hasPendingDocument;
        @endphp

        <div
            class="document-setup-row {{ $hasUsableDocument ? 'document-setup-row-attached' : '' }}"
            data-document-row="{{ $documentType }}"
            data-document-required="{{ $required ? 'true' : 'false' }}"
            data-document-existing="{{ $hasUsableDocument ? 'true' : 'false' }}"
            data-document-label="{{ $documentLabel }}"
        >
            <div>
                <strong>{{ $documentLabel }}</strong>
                <div class="small text-muted" data-file-name-for="{{ $documentType }}">
                    @if ($hasPendingDocument)
                        {{ $pendingDocument['original_name'] ?? 'Attached file' }} will be kept until approval succeeds
                    @elseif ($existingDocument)
                        Current file will be kept unless you attach a new one
                    @else
                        {{ $required ? 'Required document' : 'Optional document' }}
                    @endif
                </div>
                <div class="text-danger small mt-1 d-none" data-document-error="{{ $documentType }}"></div>
                @error($fileField['name'])
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#signingModal-{{ $documentType }}">
                Set up
            </button>
        </div>

        <input type="file" class="d-none" name="{{ $fileField['name'] }}" accept="{{ $fileField['accept'] }}"
            data-signing-file-input="{{ $documentType }}">

        @foreach (['signature', 'date'] as $fieldType)
            @php
                $storedPlacementField = collect(data_get($application->workflow_meta, "signature_fields.$documentType", []))
                    ->firstWhere('type', $fieldType);
                $oldEnabled = old("signature_fields.$documentType.$fieldType.enabled");
                $fieldEnabled = $oldEnabled !== null
                    ? (string) $oldEnabled === '1'
                    : filled($storedPlacementField);
            @endphp
            <input type="hidden" name="signature_fields[{{ $documentType }}][{{ $fieldType }}][enabled]"
                value="{{ old("signature_fields.$documentType.$fieldType.enabled", $fieldEnabled ? '1' : '0') }}" data-placement-input="{{ $documentType }}:{{ $fieldType }}:enabled">
            <input type="hidden" name="signature_fields[{{ $documentType }}][{{ $fieldType }}][page]"
                value="{{ $placementValue($documentType, $fieldType, 'page') }}" data-placement-input="{{ $documentType }}:{{ $fieldType }}:page">
            <input type="hidden" name="signature_fields[{{ $documentType }}][{{ $fieldType }}][x]"
                value="{{ $placementValue($documentType, $fieldType, 'x') }}" data-placement-input="{{ $documentType }}:{{ $fieldType }}:x">
            <input type="hidden" name="signature_fields[{{ $documentType }}][{{ $fieldType }}][y]"
                value="{{ $placementValue($documentType, $fieldType, 'y') }}" data-placement-input="{{ $documentType }}:{{ $fieldType }}:y">
            <input type="hidden" name="signature_fields[{{ $documentType }}][{{ $fieldType }}][width]"
                value="{{ $placementValue($documentType, $fieldType, 'width') }}" data-placement-input="{{ $documentType }}:{{ $fieldType }}:width">
            <input type="hidden" name="signature_fields[{{ $documentType }}][{{ $fieldType }}][height]"
                value="{{ $placementValue($documentType, $fieldType, 'height') }}" data-placement-input="{{ $documentType }}:{{ $fieldType }}:height">
        @endforeach

        <div class="modal fade signing-modal" id="signingModal-{{ $documentType }}" tabindex="-1" aria-labelledby="signingModalLabel-{{ $documentType }}" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="signingModalLabel-{{ $documentType }}">{{ $documentLabel }} Setup</h5>
                            <small class="text-muted">Upload, preview, then drag fields onto the page.</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div
                            class="signing-builder"
                            data-signing-builder="{{ $documentType }}"
                            @if ($existingDocument)
                                data-existing-document-url="{{ route('admin.document.view', $existingDocument->id) }}#toolbar=0&navpanes=0&scrollbar=1&view=FitH"
                                data-existing-document-name="{{ $existingDocument->file_name }}"
                            @endif
                        >
                            <div class="signing-preview-column">
                                <div class="signing-dropzone" data-dropzone="{{ $documentType }}">
                                    <i class="bi bi-cloud-arrow-up"></i>
                                    <strong>Drop {{ $documentLabel }} here</strong>
                                    <span>or click to attach {{ $fileField['accept'] === '.pdf' ? 'PDF' : 'document' }}</span>
                                </div>

                                <div class="pdf-preview-shell d-none" data-preview-shell="{{ $documentType }}">
                                    <div class="pdf-preview-toolbar">
                                        <span data-preview-filename="{{ $documentType }}">No file attached</span>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-reattach="{{ $documentType }}">
                                            Reattach
                                        </button>
                                    </div>
                                    <div class="pdf-canvas" data-pdf-canvas="{{ $documentType }}">
                                        <div class="pdf-document" data-pdf-document="{{ $documentType }}">
                                            <div class="pdf-loading" data-pdf-loading="{{ $documentType }}">Loading PDF…</div>
                                            <div class="pdf-pages" data-pdf-pages="{{ $documentType }}"></div>
                                            <div class="placed-field placed-field-signature d-none" data-placed-field="{{ $documentType }}:signature" draggable="true">
                                                <i class="bi bi-pencil"></i> Sign here
                                                <span class="placed-field-resize" data-resize-field="{{ $documentType }}:signature" aria-hidden="true"></span>
                                            </div>
                                            <div class="placed-field placed-field-date d-none" data-placed-field="{{ $documentType }}:date" draggable="true">
                                                <i class="bi bi-calendar3"></i> Date
                                                <span class="placed-field-resize" data-resize-field="{{ $documentType }}:date" aria-hidden="true"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="signing-tools-column">
                                <h6>Add Sign Field</h6>
                                <p class="small text-muted">Drag a field onto the document preview.</p>

                                <button type="button" class="field-tool field-tool-signature" draggable="true" data-field-tool="signature">
                                    <i class="bi bi-pencil"></i> Signature
                                </button>
                                <button type="button" class="field-tool field-tool-date" draggable="true" data-field-tool="date">
                                    <i class="bi bi-calendar3"></i> Date
                                </button>

                                <div class="field-properties">
                                    <h6>Field Properties</h6>
                                    <label class="form-label small">Selected Field</label>
                                    <input type="text" class="form-control form-control-sm" data-selected-field="{{ $documentType }}" value="Signature" readonly>

                                    <div class="field-prop-grid mt-2">
                                        <div class="field-prop-item field-prop-item-wide">
                                            <label class="form-label small">Page</label>
                                            <div class="field-number-control">
                                                <button type="button" data-field-step="{{ $documentType }}:page:-1" title="Decrease page" aria-label="Decrease page">-</button>
                                                <input type="number" class="form-control form-control-sm" min="1" data-field-prop="{{ $documentType }}:page" value="1">
                                                <button type="button" data-field-step="{{ $documentType }}:page:1" title="Increase page" aria-label="Increase page">+</button>
                                            </div>
                                        </div>
                                        <div class="field-prop-item">
                                            <label class="form-label small">X</label>
                                            <div class="field-number-control">
                                                <button type="button" data-field-step="{{ $documentType }}:x:-1" title="Move left" aria-label="Move left">-</button>
                                                <input type="number" class="form-control form-control-sm" min="0" step="0.1" data-field-prop="{{ $documentType }}:x">
                                                <button type="button" data-field-step="{{ $documentType }}:x:1" title="Move right" aria-label="Move right">+</button>
                                            </div>
                                        </div>
                                        <div class="field-prop-item">
                                            <label class="form-label small">Y</label>
                                            <div class="field-number-control">
                                                <button type="button" data-field-step="{{ $documentType }}:y:-1" title="Move up" aria-label="Move up">-</button>
                                                <input type="number" class="form-control form-control-sm" min="0" step="0.1" data-field-prop="{{ $documentType }}:y">
                                                <button type="button" data-field-step="{{ $documentType }}:y:1" title="Move down" aria-label="Move down">+</button>
                                            </div>
                                        </div>
                                        <div class="field-prop-item">
                                            <label class="form-label small">Width</label>
                                            <div class="field-number-control">
                                                <button type="button" data-field-step="{{ $documentType }}:width:-1" title="Decrease width" aria-label="Decrease width">-</button>
                                                <input type="number" class="form-control form-control-sm" min="10" step="0.1" data-field-prop="{{ $documentType }}:width">
                                                <button type="button" data-field-step="{{ $documentType }}:width:1" title="Increase width" aria-label="Increase width">+</button>
                                            </div>
                                        </div>
                                        <div class="field-prop-item">
                                            <label class="form-label small">Height</label>
                                            <div class="field-number-control">
                                                <button type="button" data-field-step="{{ $documentType }}:height:-1" title="Decrease height" aria-label="Decrease height">-</button>
                                                <input type="number" class="form-control form-control-sm" min="5" step="0.1" data-field-prop="{{ $documentType }}:height">
                                                <button type="button" data-field-step="{{ $documentType }}:height:1" title="Increase height" aria-label="Increase height">+</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="field-size-actions mt-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-field-size="{{ $documentType }}:decrease" title="Make selected field smaller" aria-label="Make selected field smaller">
                                            -
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-field-size="{{ $documentType }}:increase" title="Make selected field larger" aria-label="Make selected field larger">
                                            +
                                        </button>
                                    </div>
                                </div>

                                @if (!$signatureRequired)
                                    <small class="text-muted d-block mt-3">Signature field is optional for this document.</small>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Save Setup</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    @foreach ($plainUploadDocuments as $documentType => $documentLabel)
        @php
            $fileField = $fileFields[$documentType];
            $existingDocument = $application->documents->where('document_type', $documentType)->where('status', 'approved')->sortByDesc('id')->first();
            $pendingDocument = $pendingUploads[$documentType] ?? null;
            $hasPendingDocument = is_array($pendingDocument) && !empty($pendingDocument['path']);
            $hasUsableDocument = $existingDocument || $hasPendingDocument;
        @endphp

        <div
            class="document-setup-row document-setup-row-plain {{ $hasUsableDocument ? 'document-setup-row-attached' : '' }}"
            data-document-row="{{ $documentType }}"
            data-document-required="true"
            data-document-existing="{{ $hasUsableDocument ? 'true' : 'false' }}"
            data-document-label="{{ $documentLabel }}"
        >
            <div>
                <strong>{{ $documentLabel }}</strong>
                <div class="small text-muted" data-file-name-for="{{ $documentType }}">
                    @if ($hasPendingDocument)
                        {{ $pendingDocument['original_name'] ?? 'Attached file' }} will be kept until approval succeeds
                    @elseif ($existingDocument)
                        Current file will be kept unless you attach a new one
                    @else
                        Required physical-signature document
                    @endif
                </div>
                <div class="text-danger small mt-1 d-none" data-document-error="{{ $documentType }}"></div>
                @error($fileField['name'])
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
            <div class="plain-document-upload">
                <input type="file" class="form-control form-control-sm" name="{{ $fileField['name'] }}" accept="{{ $fileField['accept'] }}"
                    data-signing-file-input="{{ $documentType }}">
            </div>
        </div>
    @endforeach

    @php
        $pendingOtherDocuments = collect($pendingUploads['other_document'] ?? [])->filter(fn ($item) => is_array($item) && !empty($item['path']));
    @endphp

    <div class="other-documents-upload mt-3 {{ $pendingOtherDocuments->isNotEmpty() ? 'other-documents-upload-attached' : '' }}" data-other-documents-upload>
        <label class="form-label fw-semibold mb-1" for="other-document-files-{{ spl_object_id($application) }}">
            Other Documents <span class="text-muted fw-normal">(optional)</span>
        </label>
        <input
            type="file"
            id="other-document-files-{{ spl_object_id($application) }}"
            class="form-control"
            name="other_document_files[]"
            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
            multiple
        >
        <div class="form-text" data-other-documents-help>
            @if ($pendingOtherDocuments->isNotEmpty())
                {{ $pendingOtherDocuments->count() }} additional {{ \Illuminate\Support\Str::plural('document', $pendingOtherDocuments->count()) }} attached and will be kept until approval succeeds.
            @else
                Attach any additional documents you want to share with the applicant. You can select multiple files.
            @endif
        </div>
        @error('other_document_files')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
        @error('other_document_files.*')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>
</div>

<style>
    .onboarding-setup-panel {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 14px;
        background: #f8fafc;
    }

    body.modal-open .signing-modal {
        z-index: 2147483000 !important;
        pointer-events: auto;
    }

    body.modal-open .modal-backdrop {
        z-index: 2147482990 !important;
    }

    .signing-modal .modal-dialog {
        position: relative;
        z-index: 2147483001 !important;
        pointer-events: auto;
        max-width: min(1620px, calc(100vw - 48px));
    }

    .signing-modal .modal-content {
        pointer-events: auto;
        max-height: calc(100vh - 72px);
        overflow: hidden;
    }

    .signing-modal .modal-body {
        overflow: hidden;
        padding: 0 22px;
    }

    .signing-modal .modal-header,
    .signing-modal .modal-footer {
        flex: 0 0 auto;
    }

    .document-setup-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 12px 16px;
        align-items: center;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 16px;
        background: #fff;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.035);
    }

    .plain-document-upload {
        width: 100%;
        min-width: 0;
    }

    .document-setup-row-plain {
        grid-template-columns: 1fr;
        align-items: stretch;
        gap: 12px;
        margin-top: 16px;
    }

    .document-setup-row-plain strong {
        display: block;
        overflow-wrap: anywhere;
    }

    .document-setup-row-plain .plain-document-upload input {
        width: 100%;
        min-height: 46px;
        padding: 8px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        background-color: #fff;
        font-size: 0.95rem;
    }

    .document-setup-row-plain .plain-document-upload input:focus {
        border-color: #2a9d8f;
        box-shadow: 0 0 0 0.18rem rgba(42, 157, 143, 0.12);
    }

    .document-setup-row-attached {
        border-color: #9bd6b4;
        background: #f0fbf5;
        box-shadow: 0 8px 20px rgba(22, 163, 74, 0.08);
    }

    .document-setup-row-missing {
        border-color: #f2a2a2;
        background: #fff5f5;
    }

    .other-documents-upload-attached {
        border: 1px solid #9bd6b4;
        border-radius: 10px;
        padding: 12px;
        background: #f0fbf5;
    }

    .document-setup-row + .document-setup-row {
        margin-top: 16px;
    }

    .document-setup-row + input + input + input + input + input + input + input + input + input + input + input + .modal + .document-setup-row {
        margin-top: 18px;
    }

    .other-documents-upload {
        margin-top: 22px !important;
    }

    .signing-builder {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 300px;
        gap: 18px;
        height: calc(100vh - 238px);
        min-height: 540px;
        overflow: hidden;
    }

    .signing-preview-column {
        min-width: 0;
        min-height: 0;
    }

    .signing-dropzone {
        min-height: 100%;
        display: grid;
        place-items: center;
        align-content: center;
        gap: 8px;
        border: 2px dashed #9ccfc8;
        border-radius: 14px;
        background: #f2fbfa;
        color: #136f67;
        cursor: pointer;
        text-align: center;
    }

    .signing-dropzone i {
        font-size: 2rem;
    }

    .pdf-preview-shell {
        border: 1px solid #d8dee4;
        border-radius: 12px;
        overflow: hidden;
        background: #111827;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .pdf-preview-toolbar {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: center;
        padding: 10px 12px;
        background: #f8fafc;
        color: #1f2937;
        font-weight: 700;
        font-size: 0.85rem;
    }

    .pdf-canvas {
        position: relative;
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        overscroll-behavior: contain;
        background: #2b333c;
        padding: 18px;
    }

    .pdf-document,
    .pdf-pages {
        width: 100%;
    }

    .pdf-pages {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 18px;
    }

    .pdf-loading {
        display: grid;
        min-height: 240px;
        place-items: center;
        color: #fff;
        font-weight: 700;
    }

    .pdf-loading.d-none + .pdf-pages:empty::after {
        content: 'The PDF preview could not be loaded.';
        display: block;
        color: #fff;
        text-align: center;
        padding: 40px 16px;
    }

    .pdf-page-stage {
        position: relative;
        flex: 0 0 auto;
        width: min(100%, 760px);
        background: #fff;
        box-shadow: 0 14px 35px rgba(15, 23, 42, 0.24);
    }

    .pdf-page-stage canvas {
        display: block;
        width: 100%;
        height: 100%;
    }

    .placed-field {
        position: absolute;
        left: 55%;
        top: 62%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-width: 120px;
        min-height: 40px;
        padding: 8px 14px;
        border-radius: 8px;
        font-weight: 800;
        cursor: move;
        z-index: 4;
        user-select: none;
    }

    .placed-field-resize {
        position: absolute;
        right: -7px;
        bottom: -7px;
        width: 16px;
        height: 16px;
        border: 2px solid currentColor;
        border-radius: 50%;
        background: #fff;
        cursor: nwse-resize;
        box-shadow: 0 2px 7px rgba(15, 23, 42, 0.18);
    }

    .placed-field-signature {
        border: 2px dashed #f59e0b;
        background: rgba(255, 248, 219, 0.9);
        color: #92400e;
    }

    .placed-field-date {
        left: 55%;
        top: 74%;
        min-width: 92px;
        min-height: 34px;
        border: 2px dashed #3b82f6;
        background: rgba(239, 246, 255, 0.92);
        color: #1d4ed8;
    }

    .signing-tools-column {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 14px;
        background: #fff;
        overflow-y: auto;
        min-height: 0;
    }

    .field-tool {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 10px;
        border-radius: 9px;
        padding: 12px;
        margin-bottom: 10px;
        font-weight: 800;
    }

    .field-tool-signature {
        color: #92400e;
        background: #fff8db;
        border: 1px solid #f7d36a;
    }

    .field-tool-date {
        color: #1d4ed8;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
    }

    .field-properties {
        margin-top: 18px;
        padding-top: 16px;
        border-top: 1px solid #e5e7eb;
    }

    .field-prop-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px 12px;
    }

    .field-prop-item-wide {
        grid-column: 1 / -1;
    }

    .field-prop-item .form-label {
        margin-bottom: 5px;
        color: #1f3a5f;
        font-weight: 800;
    }

    .field-number-control {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr) 34px;
        align-items: stretch;
        min-height: 44px;
        border: 2px solid #e5e7eb;
        border-radius: 9px;
        background: #fff;
        overflow: hidden;
    }

    .field-number-control:focus-within {
        border-color: #9ccfc8;
        box-shadow: 0 0 0 0.18rem rgba(42, 157, 143, 0.12);
    }

    .field-number-control button {
        border: 0;
        background: #f8fafc;
        color: #1f3a5f;
        font-size: 1.1rem;
        font-weight: 900;
        line-height: 1;
    }

    .field-number-control button:hover,
    .field-number-control button:focus-visible {
        background: #eaf2fb;
        color: #0b5ed7;
    }

    .field-number-control input {
        min-width: 0;
        border: 0;
        border-radius: 0;
        box-shadow: none !important;
        text-align: center;
        padding-left: 4px;
        padding-right: 4px;
        appearance: textfield;
        -moz-appearance: textfield;
    }

    .field-number-control input::-webkit-outer-spin-button,
    .field-number-control input::-webkit-inner-spin-button {
        margin: 0;
        -webkit-appearance: none;
    }

    .field-size-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }

    .field-size-actions .btn {
        min-height: 44px;
        font-size: 1.25rem;
        font-weight: 900;
        line-height: 1;
    }

    @media (max-width: 992px) {
        .signing-modal .modal-dialog {
            max-width: calc(100vw - 20px);
        }

        .signing-modal .modal-content {
            max-height: calc(100vh - 24px);
        }

        .signing-modal .modal-body {
            overflow: auto;
            padding: 0 14px;
        }

        .signing-builder {
            grid-template-columns: 1fr;
            height: auto;
            min-height: 0;
            overflow: visible;
        }

        .pdf-canvas,
        .signing-dropzone {
            height: 520px;
            min-height: 520px;
        }

        .signing-tools-column {
            overflow: visible;
        }

    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.signing-modal').forEach((modal) => {
            document.body.appendChild(modal);

            if (window.bootstrap?.Tooltip) {
                modal.querySelectorAll('button[title]').forEach((button) => {
                    new bootstrap.Tooltip(button, { container: 'body' });
                });
            }

            modal.addEventListener('shown.bs.modal', () => {
                modal.style.zIndex = '2147483000';
                document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
                    backdrop.style.zIndex = '2147482990';
                });
            });
        });

        function documentRowFor(docType) {
            return document.querySelector(`[data-document-row="${docType}"]`);
        }

        function documentErrorFor(docType) {
            return document.querySelector(`[data-document-error="${docType}"]`);
        }

        function markDocumentAttached(docType, label) {
            const row = documentRowFor(docType);
            const error = documentErrorFor(docType);

            row?.classList.add('document-setup-row-attached');
            row?.classList.remove('document-setup-row-missing');

            if (row) {
                row.dataset.documentExisting = 'true';
            }

            if (error) {
                error.textContent = '';
                error.classList.add('d-none');
            }

            if (label) {
                const filename = document.querySelector(`[data-file-name-for="${docType}"]`);
                if (filename) {
                    filename.textContent = label;
                }
            }
        }

        function markDocumentMissing(docType, message) {
            const row = documentRowFor(docType);
            const error = documentErrorFor(docType);

            row?.classList.remove('document-setup-row-attached');
            row?.classList.add('document-setup-row-missing');

            if (error) {
                error.textContent = message;
                error.classList.remove('d-none');
            }
        }

        document.querySelectorAll('[data-signing-setup]').forEach((setup) => {
            const otherDocumentsInput = setup.querySelector('input[name="other_document_files[]"]');
            const otherDocumentsWrap = setup.querySelector('[data-other-documents-upload]');
            const otherDocumentsHelp = setup.querySelector('[data-other-documents-help]');

            otherDocumentsInput?.addEventListener('change', () => {
                otherDocumentsWrap?.classList.toggle('other-documents-upload-attached', otherDocumentsInput.files.length > 0);

                if (otherDocumentsHelp && otherDocumentsInput.files.length > 0) {
                    otherDocumentsHelp.textContent = `${otherDocumentsInput.files.length} additional ${otherDocumentsInput.files.length === 1 ? 'document' : 'documents'} attached and will be kept until approval succeeds.`;
                }
            });

            setup.querySelectorAll('[data-signing-file-input]').forEach((input) => {
                input.addEventListener('change', () => {
                    const docType = input.dataset.signingFileInput;
                    if (input.files?.[0]) {
                        markDocumentAttached(docType, input.files[0].name);
                    }
                });
            });

            setup.closest('form')?.addEventListener('submit', (event) => {
                const missingRows = Array.from(setup.querySelectorAll('[data-document-row]')).filter((row) => {
                    if (row.dataset.documentRequired !== 'true') {
                        return false;
                    }

                    const docType = row.dataset.documentRow;
                    const input = document.querySelector(`[data-signing-file-input="${docType}"]`);

                    return row.dataset.documentExisting !== 'true' && (!input?.files || input.files.length === 0);
                });

                if (missingRows.length === 0) {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();

                missingRows.forEach((row) => {
                    markDocumentMissing(row.dataset.documentRow, `${row.dataset.documentLabel} is required before issuing the onboarding package.`);
                });

                missingRows[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, true);
        });

        document.querySelectorAll('[data-signing-builder]').forEach((builder) => {
            const docType = builder.dataset.signingBuilder;
            const fileInput = document.querySelector(`[data-signing-file-input="${docType}"]`);
            const dropzone = builder.querySelector(`[data-dropzone="${docType}"]`);
            const shell = builder.querySelector(`[data-preview-shell="${docType}"]`);
            const canvas = builder.querySelector(`[data-pdf-canvas="${docType}"]`);
            const pdfDocument = builder.querySelector(`[data-pdf-document="${docType}"]`);
            const pages = builder.querySelector(`[data-pdf-pages="${docType}"]`);
            const loading = builder.querySelector(`[data-pdf-loading="${docType}"]`);
            const previewFilename = builder.querySelector(`[data-preview-filename="${docType}"]`);
            const reattach = builder.querySelector(`[data-reattach="${docType}"]`);
            const existingDocumentUrl = builder.dataset.existingDocumentUrl;
            const existingDocumentName = builder.dataset.existingDocumentName;
            const modal = builder.closest('.signing-modal');
            let selectedFieldType = 'signature';
            let resizingField = null;
            let pageCount = 0;
            let renderVersion = 0;

            const mmPage = { width: 210, height: 297 };

            function inputFor(type, key) {
                return document.querySelector(`[data-placement-input="${docType}:${type}:${key}"]`);
            }

            function propInput(key) {
                return builder.querySelector(`[data-field-prop="${docType}:${key}"]`);
            }

            function fieldEl(type) {
                return builder.querySelector(`[data-placed-field="${docType}:${type}"]`);
            }

            function pageStage(pageNumber) {
                const number = Math.max(1, Math.min(Number(pageNumber) || 1, pageCount || 1));
                return pages.querySelector(`[data-pdf-page="${number}"]`) || pages.querySelector('[data-pdf-page]');
            }

            function fieldStage(type) {
                return fieldEl(type).closest('[data-pdf-page]') || pageStage(inputFor(type, 'page')?.value);
            }

            function toMmX(px, stage) {
                return (px / stage.clientWidth) * mmPage.width;
            }

            function toMmY(px, stage) {
                return (px / stage.clientHeight) * mmPage.height;
            }

            function toPxX(mm, stage) {
                return (Number(mm) / mmPage.width) * stage.clientWidth;
            }

            function toPxY(mm, stage) {
                return (Number(mm) / mmPage.height) * stage.clientHeight;
            }

            function ensureFieldVisible(type, targetStage = null) {
                const field = fieldEl(type);
                const stage = targetStage || fieldStage(type);

                if (!stage) {
                    return field;
                }

                if (field.parentElement !== stage) {
                    stage.appendChild(field);
                }

                field.classList.remove('d-none');
                inputFor(type, 'enabled').value = '1';

                if (!field.style.width) {
                    field.style.width = `${toPxX(type === 'date' ? 38 : 65, stage)}px`;
                }

                if (!field.style.height) {
                    field.style.height = `${toPxY(type === 'date' ? 10 : 18, stage)}px`;
                }

                return field;
            }

            function clearField(type) {
                const field = fieldEl(type);
                field.classList.add('d-none');
                field.style.left = '';
                field.style.top = '';
                field.style.width = '';
                field.style.height = '';
                ['enabled', 'page', 'x', 'y', 'width', 'height'].forEach((key) => {
                    const input = inputFor(type, key);
                    if (input) {
                        input.value = key === 'enabled' ? '0' : '';
                    }
                });
            }

            function clearAllFields() {
                clearField('signature');
                clearField('date');
                selectedFieldType = 'signature';
                builder.querySelector(`[data-selected-field="${docType}"]`).value = 'Signature';
                ['x', 'y', 'width', 'height'].forEach((key) => {
                    propInput(key).value = '';
                });
                propInput('page').value = 1;
            }

            function writePlacement(type, leftPx, topPx) {
                const field = ensureFieldVisible(type);
                const stage = fieldStage(type);
                if (!stage) return;
                const widthPx = field.offsetWidth;
                const heightPx = field.offsetHeight;
                inputFor(type, 'page').value = stage.dataset.pdfPage;
                inputFor(type, 'x').value = toMmX(leftPx, stage).toFixed(1);
                inputFor(type, 'y').value = toMmY(topPx, stage).toFixed(1);
                inputFor(type, 'width').value = toMmX(widthPx, stage).toFixed(1);
                inputFor(type, 'height').value = toMmY(heightPx, stage).toFixed(1);
                field.style.left = `${(leftPx / stage.clientWidth) * 100}%`;
                field.style.top = `${(topPx / stage.clientHeight) * 100}%`;
                field.style.width = `${(widthPx / stage.clientWidth) * 100}%`;
                field.style.height = `${(heightPx / stage.clientHeight) * 100}%`;
                syncProperties(type);
            }

            function clampSize(type, widthPx, heightPx) {
                const stage = fieldStage(type);
                const minimums = type === 'date'
                    ? { width: 70, height: 30 }
                    : { width: 120, height: 42 };

                return {
                    width: Math.max(minimums.width, Math.min(widthPx, stage?.clientWidth || widthPx)),
                    height: Math.max(minimums.height, Math.min(heightPx, stage?.clientHeight || heightPx)),
                };
            }

            function resizeField(type, widthPx, heightPx) {
                const field = ensureFieldVisible(type);
                const stage = fieldStage(type);
                if (!stage) return;
                const size = clampSize(type, widthPx, heightPx);
                const maxWidth = Math.max(type === 'date' ? 70 : 120, stage.clientWidth - field.offsetLeft);
                const maxHeight = Math.max(type === 'date' ? 30 : 42, stage.clientHeight - field.offsetTop);

                field.style.width = `${Math.min(size.width, maxWidth)}px`;
                field.style.height = `${Math.min(size.height, maxHeight)}px`;
                writePlacement(type, field.offsetLeft, field.offsetTop);
            }

            function syncProperties(type) {
                selectedFieldType = type;
                builder.querySelector(`[data-selected-field="${docType}"]`).value = type === 'date' ? 'Date' : 'Signature';
                ['page', 'x', 'y', 'width', 'height'].forEach((key) => {
                    propInput(key).value = inputFor(type, key).value;
                });
            }

            function placeFromHidden(type) {
                if ((inputFor(type, 'enabled')?.value || '0') !== '1') {
                    return;
                }

                const values = ['page', 'x', 'y', 'width', 'height'].map((key) => inputFor(type, key)?.value);
                if (values.some((value) => value === undefined || value === null || value === '')) {
                    return;
                }

                const stage = pageStage(inputFor(type, 'page').value);
                if (!stage) return;
                const field = ensureFieldVisible(type, stage);
                field.style.left = `${(Number(inputFor(type, 'x').value || 0) / mmPage.width) * 100}%`;
                field.style.top = `${(Number(inputFor(type, 'y').value || 0) / mmPage.height) * 100}%`;
                field.style.width = `${(Number(inputFor(type, 'width').value || 42) / mmPage.width) * 100}%`;
                field.style.height = `${(Number(inputFor(type, 'height').value || 12) / mmPage.height) * 100}%`;
            }

            function placeSavedFieldsWhenReady() {
                if (!pageStage(1)) return;
                placeFromHidden('signature');
                placeFromHidden('date');
                if ((inputFor('signature', 'enabled')?.value || '0') === '1') {
                    syncProperties('signature');
                } else if ((inputFor('date', 'enabled')?.value || '0') === '1') {
                    syncProperties('date');
                } else {
                    syncProperties('signature');
                }
            }

            function applyPropertiesToSelectedField() {
                ['page', 'x', 'y', 'width', 'height'].forEach((key) => {
                    inputFor(selectedFieldType, key).value = propInput(key).value;
                });
                const stage = pageStage(inputFor(selectedFieldType, 'page').value);
                if (!stage) return;
                const field = ensureFieldVisible(selectedFieldType, stage);
                field.style.left = `${(Number(inputFor(selectedFieldType, 'x').value || 0) / mmPage.width) * 100}%`;
                field.style.top = `${(Number(inputFor(selectedFieldType, 'y').value || 0) / mmPage.height) * 100}%`;
                field.style.width = `${(Number(inputFor(selectedFieldType, 'width').value || 40) / mmPage.width) * 100}%`;
                field.style.height = `${(Number(inputFor(selectedFieldType, 'height').value || 12) / mmPage.height) * 100}%`;
                writePlacement(selectedFieldType, field.offsetLeft, field.offsetTop);
            }

            function visiblePageStage() {
                const viewport = canvas.getBoundingClientRect();
                return Array.from(pages.querySelectorAll('[data-pdf-page]')).reduce((closest, item) => {
                    const rect = item.getBoundingClientRect();
                    const distance = Math.abs(rect.top - viewport.top);
                    return !closest || distance < closest.distance ? { item, distance } : closest;
                }, null)?.item || pageStage(1);
            }

            async function renderPdf(source) {
                const version = ++renderVersion;
                const fields = ['signature', 'date'].map(fieldEl);
                fields.forEach((field) => pdfDocument.appendChild(field));
                pages.replaceChildren();
                loading.textContent = 'Loading PDF…';
                loading.classList.remove('d-none');
                pageCount = 0;

                try {
                    if (!window.pdfjsLib) {
                        throw new Error('PDF renderer is unavailable. Please refresh the page.');
                    }

                    const task = window.pdfjsLib.getDocument(source);
                    const pdf = await task.promise;
                    if (version !== renderVersion) return;
                    pageCount = pdf.numPages;
                    propInput('page').max = pageCount;

                    for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
                        const page = await pdf.getPage(pageNumber);
                        if (version !== renderVersion) return;
                        const viewport = page.getViewport({ scale: 1.5 });
                        const outputScale = Math.min(window.devicePixelRatio || 1, 2);
                        const stage = document.createElement('div');
                        const pageCanvas = document.createElement('canvas');
                        const context = pageCanvas.getContext('2d');

                        stage.className = 'pdf-page-stage';
                        stage.dataset.pdfPage = pageNumber;
                        stage.style.aspectRatio = `${viewport.width} / ${viewport.height}`;
                        pageCanvas.width = Math.floor(viewport.width * outputScale);
                        pageCanvas.height = Math.floor(viewport.height * outputScale);
                        pageCanvas.setAttribute('aria-label', `PDF page ${pageNumber}`);
                        stage.appendChild(pageCanvas);
                        pages.appendChild(stage);

                        await page.render({
                            canvasContext: context,
                            viewport,
                            transform: outputScale === 1 ? null : [outputScale, 0, 0, outputScale, 0, 0],
                        }).promise;
                    }

                    loading.classList.add('d-none');
                    placeSavedFieldsWhenReady();
                } catch (error) {
                    if (version !== renderVersion) return;
                    loading.textContent = error?.message || 'The PDF preview could not be loaded.';
                }
            }

            async function attachFile(file) {
                if (!file) return;
                const transfer = new DataTransfer();
                transfer.items.add(file);
                fileInput.files = transfer.files;
                clearAllFields();
                dropzone.classList.add('d-none');
                shell.classList.remove('d-none');
                markDocumentAttached(docType, file.name);
                previewFilename.textContent = file.name;
                await renderPdf({ data: new Uint8Array(await file.arrayBuffer()) });
            }

            function loadExistingDocument() {
                if (!existingDocumentUrl) {
                    return;
                }

                dropzone.classList.add('d-none');
                shell.classList.remove('d-none');
                previewFilename.textContent = existingDocumentName || 'Current document';
                renderPdf({ url: existingDocumentUrl.split('#')[0], withCredentials: true });
            }

            dropzone.addEventListener('click', () => fileInput.click());
            reattach.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', () => attachFile(fileInput.files[0]));

            dropzone.addEventListener('dragover', (event) => {
                event.preventDefault();
                dropzone.classList.add('border-primary');
            });
            dropzone.addEventListener('dragleave', () => dropzone.classList.remove('border-primary'));
            dropzone.addEventListener('drop', (event) => {
                event.preventDefault();
                dropzone.classList.remove('border-primary');
                attachFile(event.dataTransfer.files[0]);
            });

            builder.querySelectorAll('[data-field-tool]').forEach((tool) => {
                tool.addEventListener('dragstart', (event) => {
                    event.dataTransfer.setData('text/plain', tool.dataset.fieldTool);
                });

                tool.addEventListener('click', () => {
                    const type = tool.dataset.fieldTool;
                    const stage = visiblePageStage();
                    if (!stage) return;
                    propInput('page').value = stage.dataset.pdfPage;
                    const field = ensureFieldVisible(type, stage);
                    const left = Math.max(0, (stage.clientWidth - field.offsetWidth) / 2);
                    const top = Math.max(0, Math.min(80, stage.clientHeight - field.offsetHeight));
                    field.style.left = `${Math.min(left, stage.clientWidth - field.offsetWidth)}px`;
                    field.style.top = `${Math.min(top, stage.clientHeight - field.offsetHeight)}px`;
                    writePlacement(type, field.offsetLeft, field.offsetTop);
                });
            });

            canvas.addEventListener('dragover', (event) => event.preventDefault());
            pages.addEventListener('drop', (event) => {
                event.preventDefault();
                const stage = event.target.closest('[data-pdf-page]');
                if (!stage) return;
                const type = event.dataTransfer.getData('text/plain') || selectedFieldType;
                const field = ensureFieldVisible(type, stage);
                const rect = stage.getBoundingClientRect();
                const left = Math.max(0, Math.min(event.clientX - rect.left - field.offsetWidth / 2, stage.clientWidth - field.offsetWidth));
                const top = Math.max(0, Math.min(event.clientY - rect.top - field.offsetHeight / 2, stage.clientHeight - field.offsetHeight));
                field.style.left = `${left}px`;
                field.style.top = `${top}px`;
                writePlacement(type, left, top);
            });

            builder.querySelectorAll('[data-placed-field]').forEach((field) => {
                const type = field.dataset.placedField.split(':')[1];
                field.addEventListener('click', () => syncProperties(type));
                field.addEventListener('dragstart', (event) => {
                    if (resizingField) {
                        event.preventDefault();
                        return;
                    }

                    event.dataTransfer.setData('text/plain', type);
                });
                field.addEventListener('dragend', () => {
                    writePlacement(type, field.offsetLeft, field.offsetTop);
                });
            });

            builder.querySelectorAll('[data-resize-field]').forEach((handle) => {
                handle.addEventListener('pointerdown', (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    const type = handle.dataset.resizeField.split(':')[1];
                    const field = fieldEl(type);
                    resizingField = {
                        type,
                        startX: event.clientX,
                        startY: event.clientY,
                        width: field.offsetWidth,
                        height: field.offsetHeight,
                    };

                    syncProperties(type);
                    handle.setPointerCapture(event.pointerId);
                });

                handle.addEventListener('pointermove', (event) => {
                    if (!resizingField) return;

                    resizeField(
                        resizingField.type,
                        resizingField.width + event.clientX - resizingField.startX,
                        resizingField.height + event.clientY - resizingField.startY
                    );
                });

                handle.addEventListener('pointerup', (event) => {
                    if (!resizingField) return;

                    handle.releasePointerCapture(event.pointerId);
                    writePlacement(resizingField.type, fieldEl(resizingField.type).offsetLeft, fieldEl(resizingField.type).offsetTop);
                    resizingField = null;
                });
            });

            builder.querySelectorAll(`[data-field-size^="${docType}:"]`).forEach((button) => {
                button.addEventListener('click', () => {
                    const direction = button.dataset.fieldSize.split(':')[1];
                    const field = ensureFieldVisible(selectedFieldType);
                    const scale = direction === 'increase' ? 1.12 : 0.88;

                    resizeField(selectedFieldType, field.offsetWidth * scale, field.offsetHeight * scale);
                });
            });

            builder.querySelectorAll(`[data-field-step^="${docType}:"]`).forEach((button) => {
                button.addEventListener('click', () => {
                    const [, key, direction] = button.dataset.fieldStep.split(':');
                    ensureFieldVisible(selectedFieldType);
                    syncProperties(selectedFieldType);

                    const input = propInput(key);
                    const minimum = Number(input.getAttribute('min') || 0);
                    const step = key === 'page' ? 1 : 1;
                    const current = Number(input.value || minimum);
                    const maximum = key === 'page' ? pageCount || 1 : Number.POSITIVE_INFINITY;
                    const nextValue = Math.min(maximum, Math.max(minimum, current + (Number(direction) * step)));

                    input.value = key === 'page' ? Math.round(nextValue) : nextValue.toFixed(1);
                    applyPropertiesToSelectedField();
                });
            });

            builder.querySelectorAll('[data-field-prop]').forEach((input) => {
                input.addEventListener('input', () => {
                    applyPropertiesToSelectedField();
                });
            });

            syncProperties('signature');
            loadExistingDocument();

            modal?.addEventListener('shown.bs.modal', () => {
                window.requestAnimationFrame(() => placeSavedFieldsWhenReady());
            });
        });
    });
</script>
