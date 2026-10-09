@php
    $selectedUserIds = collect(old('selected_user_ids', $selectedUserIds ?? []))->map(fn ($id) => (string) $id)->all();
    $applicableUsers = old('applicable_users', $coupon->applicable_users ?? 'all_users');
    $discountType = old('discount_type', $coupon->discount_type ?? 'percentage');
    $appliesTo = old('applies_to', $coupon->applies_to ?? 'all_services');
    $startsAt = old('starts_at', optional($coupon->starts_at)->format('Y-m-d\TH:i'));
    $endsAt = old('ends_at', optional($coupon->ends_at)->format('Y-m-d\TH:i'));
@endphp

<style>
    .coupon-page {
        background: #f7f9fc;
        min-height: calc(100vh - 80px);
        padding: 2rem 0;
    }

    .coupon-shell {
        max-width: 1320px;
        margin: 0 auto;
    }

    .coupon-card {
        background: #fff;
        border: 1px solid #e6eaf2;
        border-radius: 14px;
        box-shadow: 0 12px 36px rgba(15, 23, 42, 0.08);
        padding: 1.4rem;
    }

    .coupon-form-panel,
    .coupon-preview-panel {
        border: 1px solid #e4e8f0;
        border-radius: 12px;
        background: #fff;
    }

    .coupon-section {
        padding: 1.25rem;
        border-bottom: 1px solid #e8ecf3;
    }

    .coupon-section:last-child {
        border-bottom: 0;
    }

    .coupon-section-title {
        color: #0f172a;
        font-size: 1rem;
        font-weight: 800;
        margin-bottom: 1rem;
    }

    .coupon-label {
        color: #0f172a;
        font-size: 0.82rem;
        font-weight: 700;
        margin-bottom: 0.45rem;
    }

    .coupon-help {
        color: #64748b;
        font-size: 0.76rem;
        margin-top: 0.42rem;
    }

    .coupon-input,
    .coupon-select,
    .coupon-textarea {
        border: 1px solid #d9dee8;
        border-radius: 6px;
        color: #0f172a;
        font-size: 0.88rem;
        padding: 0.72rem 0.8rem;
        width: 100%;
    }

    .coupon-code-input {
        text-transform: uppercase;
    }

    .coupon-textarea {
        min-height: 84px;
        resize: vertical;
    }

    .coupon-user-picker {
        border: 1px solid #d9dee8;
        border-radius: 8px;
        display: none;
        margin-top: 0.8rem;
        overflow: hidden;
    }

    .coupon-user-picker.is-visible {
        display: block;
    }

    .coupon-user-search {
        border: 0;
        border-bottom: 1px solid #e6eaf2;
        padding: 0.75rem 0.9rem;
        width: 100%;
    }

    .coupon-user-list {
        max-height: 240px;
        overflow-y: auto;
    }

    .coupon-user-option {
        align-items: flex-start;
        border-bottom: 1px solid #eef2f7;
        cursor: pointer;
        display: flex;
        gap: 0.75rem;
        padding: 0.75rem 0.9rem;
    }

    .coupon-user-option:last-child {
        border-bottom: 0;
    }

    .coupon-user-option:hover {
        background: #f8fafc;
    }

    .coupon-ticket {
        background: linear-gradient(135deg, #5546ea, #6157f7);
        border-radius: 6px;
        color: #fff;
        margin: 1.5rem auto;
        max-width: 270px;
        overflow: hidden;
        padding: 1.8rem 1.2rem;
        position: relative;
        text-align: center;
    }

    .coupon-ticket-code {
        display: block;
        font-size: clamp(1.25rem, 5vw, 1.95rem);
        font-weight: 800;
        line-height: 1.05;
        margin: 0 auto 0.85rem;
        max-width: 100%;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .coupon-ticket-copy {
        font-size: 0.95rem;
        line-height: 1.45;
        margin: 0.95rem auto 0;
        max-width: 210px;
        overflow-wrap: anywhere;
    }

    .coupon-ticket::before,
    .coupon-ticket::after {
        background: #fff;
        border-radius: 999px;
        content: "";
        height: 28px;
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 28px;
    }

    .coupon-ticket::before {
        left: -14px;
    }

    .coupon-ticket::after {
        right: -14px;
    }

    .coupon-preview-row {
        align-items: center;
        border-bottom: 1px solid #edf0f5;
        color: #0f172a;
        display: flex;
        font-size: 0.86rem;
        gap: 0.8rem;
        justify-content: space-between;
        padding: 0.92rem 0;
    }

    .coupon-preview-row:last-child {
        border-bottom: 0;
    }

    @media (max-width: 991px) {
        .coupon-preview-panel {
            margin-top: 1rem;
        }
    }
</style>

<div class="coupon-page">
    <div class="coupon-shell px-3">
        <div class="coupon-card">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <h2 class="h4 fw-bold mb-2">{{ $title }}</h2>
                    <p class="text-muted mb-0">{{ $subtitle }}</p>
                </div>
                <a href="{{ route('admin.discount-coupons.index') }}" class="btn btn-outline-secondary btn-sm">
                    Back to Coupons
                </a>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Please fix the highlighted fields.</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="POST" action="{{ $action }}" class="row g-4" id="couponForm">
                @csrf
                @if ($method !== 'POST')
                    @method($method)
                @endif

                <div class="col-lg-9">
                    <div class="coupon-form-panel">
                        <div class="row g-0">
                            <div class="col-lg-7">
                                <div class="coupon-section">
                                    <div class="coupon-section-title">Basic Information</div>
                                    <div class="mb-3">
                                        <label class="coupon-label">Coupon Code <span class="text-danger">*</span></label>
                                        <input type="text" name="code" class="coupon-input coupon-code-input" id="couponCode"
                                            value="{{ old('code', $coupon->code) }}"
                                            placeholder="Enter coupon code (e.g. TM50)" required>
                                        <div class="coupon-help">Customers will enter this code at checkout.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="coupon-label">Coupon Title <span class="text-danger">*</span></label>
                                        <input type="text" name="title" class="coupon-input"
                                            value="{{ old('title', $coupon->title) }}"
                                            placeholder="Enter a title for this coupon" required>
                                        <div class="coupon-help">For admin reference only.</div>
                                    </div>
                                    <div>
                                        <label class="coupon-label">Description</label>
                                        <textarea name="description" class="coupon-textarea" placeholder="Enter description (optional)">{{ old('description', $coupon->description) }}</textarea>
                                    </div>
                                </div>

                                <div class="coupon-section">
                                    <div class="coupon-section-title">Discount Type</div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="coupon-label">Discount Type <span class="text-danger">*</span></label>
                                            <select name="discount_type" class="coupon-select" id="discountType" required>
                                                <option value="percentage" @selected($discountType === 'percentage')>Percentage (%)</option>
                                                <option value="flat" @selected($discountType === 'flat')>Flat Amount (£)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="coupon-label">Discount Value <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" min="0.01" name="discount_value"
                                                class="coupon-input" id="discountValue"
                                                value="{{ old('discount_value', $coupon->discount_value) }}"
                                                placeholder="e.g. 20" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="coupon-section">
                                    <div class="coupon-section-title">Applies To</div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="coupon-label">Applies To <span class="text-danger">*</span></label>
                                            <select name="applies_to" class="coupon-select" id="appliesTo" required>
                                                <option value="all_services" @selected($appliesTo === 'all_services')>All Services</option>
                                                <option value="trademark_filing" @selected($appliesTo === 'trademark_filing')>UK Trade Mark Filing</option>
                                                <option value="uk_search" @selected($appliesTo === 'uk_search')>UK Trade Mark Search</option>
                                                <option value="uk_examination_response" @selected($appliesTo === 'uk_examination_response')>UK Examination Response</option>
                                            </select>
                                            <div class="coupon-help">Select specific services or categories.</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="coupon-section">
                                    <div class="coupon-section-title">Usage Limits</div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="coupon-label">Usage Limit (Total)</label>
                                            <input type="number" min="1" name="usage_limit" class="coupon-input"
                                                value="{{ old('usage_limit', $coupon->usage_limit) }}"
                                                placeholder="e.g. 100">
                                            <div class="coupon-help">Total number of times this coupon can be used.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="coupon-label">Per User Limit</label>
                                            <input type="number" min="1" name="per_user_limit" class="coupon-input"
                                                value="{{ old('per_user_limit', $coupon->per_user_limit) }}"
                                                placeholder="e.g. 1">
                                            <div class="coupon-help">How many times a single user can use this coupon.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-5 border-start">
                                <div class="coupon-section">
                                    <div class="coupon-section-title">Availability</div>
                                    <div class="mb-3">
                                        <label class="coupon-label">Start Date & Time</label>
                                        <input type="datetime-local" name="starts_at" class="coupon-input"
                                            value="{{ $startsAt }}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="coupon-label">End Date & Time</label>
                                        <input type="datetime-local" name="ends_at" class="coupon-input"
                                            value="{{ $endsAt }}">
                                    </div>
                                    <div class="coupon-help mb-3">Times use UK local time (GMT/BST).</div>
                                    <div>
                                        <label class="coupon-label">Applicable Users</label>
                                        <select name="applicable_users" class="coupon-select" id="applicableUsers">
                                            <option value="all_users" @selected($applicableUsers === 'all_users')>All Users</option>
                                            <option value="specific_users" @selected($applicableUsers === 'specific_users')>Specific Users</option>
                                        </select>
                                        <div class="coupon-help">Choose whether everyone can use this coupon or only selected users.</div>

                                        <div class="coupon-user-picker" id="specificUsersPicker">
                                            <input type="search" class="coupon-user-search" id="userSearch"
                                                placeholder="Search users by name, email, or user ID">
                                            <div class="coupon-user-list">
                                                @foreach ($users as $user)
                                                    <label class="coupon-user-option"
                                                        data-user-search="{{ Str::lower($user->name . ' ' . $user->email . ' #' . $user->id) }}">
                                                        <input type="checkbox" name="selected_user_ids[]"
                                                            value="{{ $user->id }}"
                                                            @checked(in_array((string) $user->id, $selectedUserIds, true))>
                                                        <span>
                                                            <strong>{{ $user->name }}</strong>
                                                            <br>
                                                            <small class="text-muted">User ID: #{{ $user->id }} · {{ $user->email }}</small>
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="coupon-section">
                                    <div class="coupon-section-title">Advanced Options</div>
                                    <div class="form-check mb-3">
                                        <input type="hidden" name="show_on_website" value="0">
                                        <input class="form-check-input" type="checkbox" name="show_on_website" value="1"
                                            id="showWebsite" @checked(old('show_on_website', $coupon->show_on_website))>
                                        <label class="form-check-label fw-bold" for="showWebsite">Show on website</label>
                                        <div class="coupon-help">Display this coupon on the website payments page.</div>
                                    </div>
                                    <div class="form-check">
                                        <input type="hidden" name="is_active" value="0">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                            id="isActive" @checked(old('is_active', $coupon->is_active))>
                                        <label class="form-check-label fw-bold" for="isActive">Active</label>
                                        <div class="coupon-help">Turn off to disable this coupon.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="coupon-section d-flex flex-wrap align-items-center justify-content-end gap-2">
                            <a href="{{ route('admin.discount-coupons.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">{{ $submitLabel }}</button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3">
                    <aside class="coupon-preview-panel p-4">
                        <div class="text-center fw-bold mb-3">Coupon Preview</div>
                        <div class="coupon-ticket">
                            <div>
                                <span class="coupon-ticket-code" id="previewCode">{{ old('code', $coupon->code ?: 'TM50') }}</span>
                                <span class="badge bg-light text-primary fs-6" id="previewDiscount">20% OFF</span>
                            </div>
                            <div class="coupon-ticket-copy" id="previewDescription">
                                {{ old('description', $coupon->description) ?: 'Get discount on your order' }}
                            </div>
                        </div>
                        <div class="coupon-preview-row">
                            <span><i class="fas fa-shield-alt text-primary me-2"></i>Applies To</span>
                            <strong id="previewApplies">All Services</strong>
                        </div>
                        <div class="coupon-preview-row">
                            <span><i class="fas fa-clock text-primary me-2"></i>Discount Type</span>
                            <strong id="previewDiscountType">20% OFF</strong>
                        </div>
                        <div class="coupon-preview-row">
                            <span><i class="fas fa-calendar text-primary me-2"></i>Validity</span>
                            <strong>Based on dates</strong>
                        </div>
                        <div class="coupon-preview-row">
                            <span><i class="fas fa-users text-primary me-2"></i>Applicable Users</span>
                            <strong id="previewUsers">All Users</strong>
                        </div>
                        <div class="alert alert-primary small mb-0 mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            This is how the coupon can appear to customers.
                        </div>
                    </aside>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const applicableUsers = document.getElementById('applicableUsers');
        const usersPicker = document.getElementById('specificUsersPicker');
        const userSearch = document.getElementById('userSearch');
        const userOptions = Array.from(document.querySelectorAll('.coupon-user-option'));
        const couponCode = document.getElementById('couponCode');
        const discountType = document.getElementById('discountType');
        const discountValue = document.getElementById('discountValue');
        const appliesTo = document.getElementById('appliesTo');
        const description = document.querySelector('textarea[name="description"]');
        const previewCode = document.getElementById('previewCode');
        const previewDiscount = document.getElementById('previewDiscount');
        const previewDiscountType = document.getElementById('previewDiscountType');
        const previewApplies = document.getElementById('previewApplies');
        const previewUsers = document.getElementById('previewUsers');
        const previewDescription = document.getElementById('previewDescription');

        function titleFromValue(value) {
            const labels = {
                all_services: 'All Services',
                trademark_filing: 'Trademark Filing',
                audit_package_purchase: 'Audit Package Purchase',
                execution_package_purchase: 'Execution Package Purchase',
                opposition_defence_package: 'Opposition Defence',
                opposition_filing: 'Opposition Filing',
                objection_reply: 'Objection Reply',
            };

            return labels[value] || 'All Services';
        }

        function toggleUserPicker() {
            const showPicker = applicableUsers.value === 'specific_users';
            usersPicker.classList.toggle('is-visible', showPicker);
            previewUsers.textContent = showPicker ? 'Specific Users' : 'All Users';
        }

        function updatePreview() {
            const code = couponCode.value.trim() || 'TM50';
            const value = discountValue.value || '20';
            const label = discountType.value === 'flat' ? '£' + value : value + '% OFF';

            previewCode.textContent = code.toUpperCase();
            previewCode.classList.add('coupon-ticket-code');
            previewDiscount.textContent = label;
            previewDiscountType.textContent = label;
            previewApplies.textContent = titleFromValue(appliesTo.value);
            previewDescription.textContent = description.value.trim() || 'Get discount on your order';
        }

        couponCode.addEventListener('input', function () {
            const cursorStart = this.selectionStart;
            const cursorEnd = this.selectionEnd;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(cursorStart, cursorEnd);
        });

        applicableUsers.addEventListener('change', toggleUserPicker);
        couponCode.addEventListener('input', updatePreview);
        discountType.addEventListener('change', updatePreview);
        discountValue.addEventListener('input', updatePreview);
        appliesTo.addEventListener('change', updatePreview);
        description.addEventListener('input', updatePreview);

        userSearch.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();
            userOptions.forEach(function (option) {
                option.style.display = option.dataset.userSearch.includes(query) ? 'flex' : 'none';
            });
        });

        toggleUserPicker();
        updatePreview();
    });
</script>
