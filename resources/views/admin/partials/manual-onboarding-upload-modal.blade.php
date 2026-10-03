<div class="modal fade admin-manual-upload-modal" id="manualOnboardingUploadModal" tabindex="-1" aria-labelledby="manualOnboardingUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.manual-onboarding-package', $application->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="manualOnboardingUploadModalLabel">Upload Documents Manually</h5>
                        <small class="text-muted">Upload a signed Engagement Letter on the client's behalf.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        Add the signed Engagement Letter. If the application is under review, this will approve it and save the letter as a verified onboarding document. POA and Affidavit are not required.
                    </div>

                    <label class="form-label fw-semibold">Signed Engagement Letter</label>
                    <input type="file" name="manual_engagement_letter_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                    @error('manual_engagement_letter_file')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror

                    <label class="form-label fw-semibold mt-3">Other Documents <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="file" name="manual_other_document_files[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" multiple>
                    @error('manual_other_document_files')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                    @error('manual_other_document_files.*')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror

                    <label class="form-label fw-semibold mt-3">Note <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea name="manual_onboarding_note" class="form-control" rows="3" placeholder="Optional note about the signed Engagement Letter">{{ old('manual_onboarding_note') }}</textarea>
                    @error('manual_onboarding_note')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" data-manual-upload-submit>Upload and Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>
