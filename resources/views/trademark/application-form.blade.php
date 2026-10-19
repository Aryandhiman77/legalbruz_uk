@extends('layouts.app')

@section('content')
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h2>Trademark Application Form</h2>
                        <small>Step 1: Basic Information</small>
                    </div>
                    <div class="card-body p-5">
                        <form action="{{ route('trademark.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf

                            <input type="hidden" name="entity_type" value="{{ $entity_type }}">

                            <!-- Applicant Name -->
                            <div class="mb-3">
                                <label for="applicant_name" class="form-label">Applicant Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('applicant_name') is-invalid @enderror"
                                    id="applicant_name" name="applicant_name" value="{{ old('applicant_name') }}" required>
                                @error('applicant_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Phone & Email -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number <span
                                            class="text-danger">*</span></label>
                                    <input type="tel" class="form-control @error('phone') is-invalid @enderror"
                                        id="phone" name="phone" value="{{ old('phone') }}" required>
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address <span
                                            class="text-danger">*</span></label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror"
                                        id="email" name="email" value="{{ old('email') }}" required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Brand Name -->
                            <div class="mb-3">
                                <label for="brand_name" class="form-label">Brand Name / Trademark <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('brand_name') is-invalid @enderror"
                                    id="brand_name" name="brand_name" value="{{ old('brand_name') }}" required>
                                @error('brand_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Logo Upload -->
                            <div class="mb-3">
                                <label for="logo" class="form-label">Logo (if applicable)</label>
                                <input type="file" class="form-control @error('logo') is-invalid @enderror"
                                    id="logo" name="logo" accept="image/*">
                                <small class="form-text text-muted">PNG, JPG, JPEG - Max 5MB</small>
                                @error('logo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label for="description" class="form-label">Description of Goods/Services <span
                                        class="text-danger">*</span></label>
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                                    rows="3" required>{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <!-- Industry -->
                                <div class="col-md-6 mb-3">
                                    <label for="industry" class="form-label">Business Industry <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('industry') is-invalid @enderror"
                                        id="industry" name="industry" value="{{ old('industry') }}" required>
                                    @error('industry')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Usage Type -->
                                <div class="col-md-6 mb-3">
                                    <label for="usage_type" class="form-label">Usage Type <span
                                            class="text-danger">*</span></label>
                                    <select class="form-select @error('usage_type') is-invalid @enderror" id="usage_type"
                                        name="usage_type" required>
                                        <option value="">Select usage type</option>
                                        <option value="uk" {{ old('usage_type') === 'uk' ? 'selected' : '' }}>United Kingdom
                                            Only</option>
                                        <option value="international"
                                            {{ old('usage_type') === 'international' ? 'selected' : '' }}>International
                                        </option>
                                        <option value="both" {{ old('usage_type') === 'both' ? 'selected' : '' }}>Both
                                        </option>
                                    </select>
                                    @error('usage_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <!-- First Use Date -->
                                <div class="col-md-6 mb-3">
                                    <label for="first_use_date" class="form-label">Date of First Use (if applicable)</label>
                                    <input type="date" class="form-control @error('first_use_date') is-invalid @enderror"
                                        id="first_use_date" name="first_use_date" value="{{ old('first_use_date') }}">
                                    @error('first_use_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Currently Selling -->
                                <div class="col-md-6 mb-3">
                                    <label for="currently_selling" class="form-label">Are you currently selling under this
                                        brand?</label>
                                    <select class="form-select @error('currently_selling') is-invalid @enderror"
                                        id="currently_selling" name="currently_selling">
                                        <option value="0" {{ old('currently_selling') == '0' ? 'selected' : '' }}>No
                                        </option>
                                        <option value="1" {{ old('currently_selling') == '1' ? 'selected' : '' }}>Yes
                                        </option>
                                    </select>
                                    @error('currently_selling')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Website -->
                            <div class="mb-3">
                                <label for="website" class="form-label">Website / Social Media (if any)</label>
                                <input type="url" class="form-control @error('website') is-invalid @enderror"
                                    id="website" name="website" value="{{ old('website') }}" placeholder="https://...">
                                @error('website')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                After submission, you'll need to complete a 50% advance payment before proceeding further.
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <a href="{{ route('trademark.kyc', $entity_type) }}"
                                        class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-arrow-left"></i> Back
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-arrow-right"></i> Continue to Payment
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
