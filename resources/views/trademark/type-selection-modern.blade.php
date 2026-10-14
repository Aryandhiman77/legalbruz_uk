@extends('layouts.app-modern')

@section('content')
    <div class="container">
        <!-- Progress Bar -->
        <div class="progress-section">
            <div class="step-tracker">
                <div class="step-item active">
                    <div class="step-number">1</div>
                    <div class="step-label">Type</div>
                </div>
                <div class="step-item">
                    <div class="step-number">2</div>
                    <div class="step-label">Required</div>
                </div>
                <div class="step-item">
                    <div class="step-number">3</div>
                    <div class="step-label">Form</div>
                </div>
                <div class="step-item">
                    <div class="step-number">4</div>
                    <div class="step-label">Payment</div>
                </div>
                <div class="step-item">
                    <div class="step-number">5</div>
                    <div class="step-label">Submit</div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h2>Select Your Entity Type</h2>
                        <small>Choose the type that best describes your business</small>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4" style="font-size: 1rem;">
                            Select the entity type that applies to your trademark registration. This helps us prepare the
                            right documents and requirements for your application.
                        </p>

                        <div class="row g-4">
                            <!-- Individual Option -->
                            <div class="col-md-6">
                                <a href="{{ route('trademark.kyc', 'individual') }}" class="option-card">
                                    <i class="bi bi-person-circle"></i>
                                    <h3>Individual</h3>
                                    <p>Single person or sole proprietor. Perfect for freelancers and personal brands.</p>
                                    <button type="button" class="btn btn-primary" onclick="event.stopPropagation();">
                                      Select
                                    </button>
                                </a>
                            </div>

                            <!-- Company Option -->
                            <div class="col-md-6">
                                <a href="{{ route('trademark.kyc', 'company') }}" class="option-card">
                                    <i class="bi bi-building"></i>
                                    <h3>Company/Business</h3>
                                    <p>Partnership, Corporation, or any registered business entity.</p>
                                    <button type="button" class="btn btn-primary" onclick="event.stopPropagation();">
                                         Select
                                    </button>
                                </a>
                            </div>
                        </div>

                        <div class="alert alert-info mt-5">
                            <i class="bi bi-info-circle"></i>
                            <strong>Need Help?</strong> If you're unsure which option applies to you, contact our support
                            team at <strong>info@legalbruz.co.uk</strong>.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
