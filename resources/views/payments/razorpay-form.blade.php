@extends('layouts.app-modern')

@section('content')
    @php
        $formatPounds = static function ($amount): string {
            $amount = (float) $amount;
            $decimals = abs($amount - round($amount)) > 0.00001 ? 2 : 0;

            return '£' . number_format($amount, $decimals);
        };
    @endphp
    <style>
        .coupon-price-original {
            background: linear-gradient(135deg, #2A9D8F 0%, #228974 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-size: 1.28rem;
            font-weight: 500;
            margin-right: 0.45rem;
            position: relative;
            white-space: nowrap;
        }

        .coupon-price-original::after {
            content: "";
            position: absolute;
            left: -3px;
            right: -3px;
            top: 52%;
            height: 3px;
            border-radius: 999px;
            background: linear-gradient(135deg, #2A9D8F 0%, #228974 100%);
            transform: rotate(-5deg);
        }

        .coupon-price-new {
            color: #5546ea;
            font-weight: 950;
            white-space: nowrap;
        }

        .coupon-auto-note {
            color: #5546ea;
            font-size: 0.78rem;
            font-weight: 800;
        }
    </style>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <!-- Payment Header -->
                <div class="card border-0 shadow-lg mb-4">
                    <div class="card-body p-5" style="background: linear-gradient(135deg, #1D3557 0%, #2A9D8F 100%);">
                        <h1 class="text-white mb-2">Payment Required</h1>
                        <p class="text-white-50 mb-0">
                            {{ ($paymentType ?? 'advance') === 'final' ? 'Final 50% balance payment' : '50% Advance Payment for Trademark Application' }}
                        </p>
                    </div>
                </div>

                <!-- Amount Details Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4" style="color: #1D3557;">📊 Payment Breakdown</h5>

                        <div class="row mb-3">
                            <div class="col-6">
                                <p class="text-muted small">Total Professional Fee</p>
                                @if (!empty($autoApplyCoupon) && ($originalTotalAmount ?? $totalAmount) > $totalAmount)
                                    <h6>
                                        <span class="coupon-price-original">{{ $formatPounds($originalTotalAmount) }}</span>
                                        <span class="coupon-price-new" data-service-total>{{ $formatPounds($totalAmount) }}</span>
                                    </h6>
                                    <div class="coupon-auto-note">{{ $autoApplyCoupon->code }}</div>
                                @else
                                    <h6 style="color: #1D3557;"><strong data-service-total>{{ $formatPounds($totalAmount) }}</strong></h6>
                                @endif
                            </div>
                            <div class="col-6">
                                <p class="text-muted small">{{ ($paymentType ?? 'advance') === 'final' ? 'Payable Now' : 'Advance (50%)' }}</p>
                                <h6 style="color: #2A9D8F;"><strong data-payable-now>{{ $formatPounds($advanceAmount) }}</strong></h6>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-6">
                                <p class="text-muted small">{{ ($paymentType ?? 'advance') === 'final' ? 'Remaining After This Payment' : 'Remaining Payment (50%)' }}</p>
                                <h6 style="color: #4A4A4A;"><strong data-remaining-balance>{{ $formatPounds(max($totalAmount - $paidServiceAmount - $advanceAmount, 0)) }}</strong></h6>
                            </div>
                            <div class="col-6">
                                <p class="text-muted small">{{ ($paymentType ?? 'advance') === 'final' ? 'Payment Stage' : 'Final Balance' }}</p>
                                <small class="text-muted">{{ ($paymentType ?? 'advance') === 'final' ? 'Due now before filing' : 'Due after client approval' }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Application Info Card -->
                @include('partials.payment-coupons', [
                    'paymentAmount' => $originalTotalAmount,
                    'selectedCouponId' => $autoApplyCoupon?->id,
                ])

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4" style="color: #1D3557;">🏷️ Application Details</h5>

                        <div class="row mb-3">
                            <div class="col-6">
                                <p class="text-muted small">Applicant Name</p>
                                <p class="mb-0"><strong>{{ $application->applicant_name }}</strong></p>
                            </div>
                            <div class="col-6">
                                <p class="text-muted small">Application Stage</p>
                                <p class="mb-0"><strong>{{ ($paymentType ?? 'advance') === 'final' ? 'Approved for filing' : 'Pre-payment intake complete' }}</strong></p>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <p class="text-muted small">Entity Type</p>
                                <p class="mb-0"><strong>{{ $application->entity_type === 'individual' ? 'Individual / Proprietor / Trader' : ucfirst($application->entity_type) }}</strong></p>
                            </div>
                            <div class="col-6">
                                <p class="text-muted small">Application Type</p>
                                <p class="mb-0"><strong>UK Trade Mark Application</strong></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Type Selector -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4" style="color: #1D3557;">💳 Payment Options</h5>

                        <div id="payment-options">
                            <!-- Primary option -->
                            <div class="payment-option mb-3" onclick="selectPaymentOption('{{ ($paymentType ?? 'advance') === 'final' ? 'final' : 'advance' }}', this)">
                                <div class="p-3 border rounded-lg cursor-pointer"
                                    style="border: 2px solid #2A9D8F !important; background: #f0f9f8;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1" style="color: #1D3557;">
                                                {{ ($paymentType ?? 'advance') === 'final' ? 'Final 50% Balance' : '50% Advance Payment' }}
                                            </h6>
                                            <small class="text-muted">
                                                {{ ($paymentType ?? 'advance') === 'final' ? 'Remaining service balance before filing' : 'Pay now, remaining after draft approval' }}
                                            </small>
                                        </div>
                                        <div>
                                            <h5 class="{{ !empty($autoApplyCoupon) ? 'coupon-price-new' : '' }}" style="{{ empty($autoApplyCoupon) ? 'color: #2A9D8F;' : '' }}" data-primary-option-amount>{{ $formatPounds($advanceAmount) }}</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Custom Amount
                            <div class="payment-option" onclick="selectPaymentOption('custom', 0)">
                                <div class="p-3 border rounded-lg cursor-pointer"
                                    style="border: 2px solid #e9ecef !important;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1" style="color: #1D3557;">Custom Amount</h6>
                                            <small class="text-muted">Pay any amount between
                                                £{{ config('razorpay.custom_payments.min_amount') }} -
                                                £{{ number_format(config('razorpay.custom_payments.max_amount'), 0) }}</small>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-outline-secondary">Enter Amount</button>
                                        </div>
                                    </div>
                                </div>
                            </div> -->
                        </div>

                        <!-- Custom Amount Input (Hidden) -->
                        <div id="custom-amount-div" style="display: none; margin-top: 15px;">
                            <label for="custom_amount" class="form-label">Enter Custom Amount (£)</label>
                            <input type="number" id="custom_amount" class="form-control"
                                placeholder="Enter amount between {{ config('razorpay.custom_payments.min_amount') }} - {{ config('razorpay.custom_payments.max_amount') }}"
                                min="{{ config('razorpay.custom_payments.min_amount') }}"
                                max="{{ config('razorpay.custom_payments.max_amount') }}">
                        </div>
                    </div>
                </div>

                <!-- Payment Method: Razorpay -->
                <div class="card border-0 shadow-sm mb-4" id="razorpay-section">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4" style="color: #1D3557;">🔐 Secure Payment</h5>

                        <div class="alert alert-info d-flex align-items-center" role="alert">
                            <i class="bi bi-shield-check me-2"></i>
                            <small>Your payment is secured with Razorpay's encryption. We accept all major credit/debit
                                cards.</small>
                        </div>

                        <img src="https://razorpay.com/favicon.ico" alt="Razorpay" style="height: 20px;">
                        <span class="ms-2 text-muted small">Powered by Razorpay</span>
                    </div>
                </div>

                @include('partials.government-fee-notice')

                <!-- Terms & Conditions -->
                <div class="alert alert-warning" role="alert">
                    <h6 class="alert-heading">⚠️ Important Notice</h6>
                    <small>
                        By proceeding with payment, you agree to our terms and conditions. Your payment is non-refundable.
                        After successful payment, you will receive further instructions via email.
                    </small>
                </div>

                <!-- Pay Button -->
                <button id="pay-button" class="btn w-100 text-white py-3 mb-3"
                    style="background: linear-gradient(135deg, #2A9D8F 0%, #1D3557 100%); font-size: 18px; font-weight: bold;">
                    <i class="bi bi-credit-card me-2"></i> Proceed to Payment
                </button>

                <div class="text-center">
                    <a href="{{ route('dashboard') }}" class="btn btn-link text-decoration-none">← Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Razorpay Checkout Script -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

    <script>
        // Initialize payment option
        let selectedPaymentType = '{{ $paymentType ?? 'advance' }}';
        let selectedAmount = {{ $advanceAmount }};
        let discountedServiceTotal = {{ $totalAmount }};
        const originalServiceTotal = {{ $originalTotalAmount }};
        const previouslyPaidAmount = {{ $paidServiceAmount ?? 0 }};

        function calculateSelectedAmount(type) {
            if (type === 'final') return Math.max(discountedServiceTotal - previouslyPaidAmount, 0);
            return Math.round(discountedServiceTotal * 50) / 100;
        }

        function formatPounds(amount) {
            const numericAmount = Number(amount);
            const hasPence = !Number.isInteger(numericAmount);

            return `£${new Intl.NumberFormat('en-GB', {
                minimumFractionDigits: hasPence ? 2 : 0,
                maximumFractionDigits: 2
            }).format(numericAmount)}`;
        }

        function refreshPaymentAmounts() {
            selectedAmount = calculateSelectedAmount(selectedPaymentType);
            const primaryType = '{{ ($paymentType ?? 'advance') === 'final' ? 'final' : 'advance' }}';
            const primaryAmount = calculateSelectedAmount(primaryType);
            document.querySelectorAll('[data-service-total]').forEach(el => el.textContent = formatPounds(discountedServiceTotal));
            document.querySelectorAll('[data-payable-now]').forEach(el => el.textContent = formatPounds(selectedAmount));
            document.querySelectorAll('[data-remaining-balance]').forEach(el => el.textContent = formatPounds(Math.max(discountedServiceTotal - previouslyPaidAmount - selectedAmount, 0)));
            document.querySelectorAll('[data-primary-option-amount]').forEach(el => el.textContent = formatPounds(primaryAmount));
        }

        function selectPaymentOption(type, element = null) {
            selectedPaymentType = type;
            document.getElementById('custom-amount-div').style.display = 'none';
            refreshPaymentAmounts();

            // Update UI
            document.querySelectorAll('.payment-option > div').forEach(el => {
                el.style.borderColor = '#e9ecef';
                el.style.background = '#fff';
            });

            if (type !== 'custom' && element) {
                const selectedCard = element.querySelector(':scope > div') ?? element.firstElementChild;

                if (selectedCard) {
                    selectedCard.style.borderColor = '#2A9D8F';
                    selectedCard.style.background = '#f0f9f8';
                }
            }
        }

        // Pay button click
        document.getElementById('pay-button').addEventListener('click', function() {
            let amount = selectedAmount;
            const minAmount = {{ config('razorpay.custom_payments.min_amount') }};
            const maxAmount = {{ config('razorpay.custom_payments.max_amount') }};

            // If custom amount selected, get value from input
            // Create Razorpay order
            createRazorpayOrder(amount);
        });

        function createRazorpayOrder(amount) {
            const button = document.getElementById('pay-button');
            window.LegalBruzButtonLoading?.set(button, 'Creating Order...');

            fetch('{{ route('payment.create-order', $application->id) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        amount: amount,
                        payment_type: selectedPaymentType,
                        discount_coupon_id: document.querySelector('.payment-coupon-radio:checked')?.value || null
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        initiateRazorpayCheckout(data);
                    } else {
                        alert('Error: ' + data.message);
                        window.LegalBruzButtonLoading?.reset(button);
                    }
                })
                .catch(error => {
                    alert('Error creating order: ' + error.message);
                    window.LegalBruzButtonLoading?.reset(button);
                });
        }

        function initiateRazorpayCheckout(orderData) {
            const options = {
                key: orderData.key,
                amount: orderData.amount,
                currency: orderData.currency,
                name: '{{ config('app.name') }}',
                description: 'UK Trade Mark Application - {{ $application->brand_name }}',
                order_id: orderData.order_id,
                handler: function(response) {
                    verifyPaymentSignature(response);
                },
                prefill: {
                    name: orderData.user_name,
                    email: orderData.user_email,
                    contact: orderData.user_phone
                },
                theme: {
                    color: '#2A9D8F'
                }
            };

            const rzp1 = new Razorpay(options);
            rzp1.open();

            document.getElementById('pay-button').disabled = false;
            window.LegalBruzButtonLoading?.reset(document.getElementById('pay-button'));
        }

        function verifyPaymentSignature(response) {
            const button = document.getElementById('pay-button');
            window.LegalBruzButtonLoading?.set(button, 'Verifying Payment...');

            fetch('{{ route('payment.verify-signature', $application->id) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_signature: response.razorpay_signature
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: 'Payment Successful!',
                            text: 'Your payment has been verified. Redirecting...',
                            allowOutsideClick: false
                        }).then(() => {
                            window.location.href = data.redirect_url;
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Verification Failed',
                            text: data.message
                        });
                        window.LegalBruzButtonLoading?.reset(button);
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Payment verification failed: ' + error.message
                    });
                    window.LegalBruzButtonLoading?.reset(button);
                });
        }

        document.querySelectorAll('.payment-coupon-radio').forEach(input => {
            input.addEventListener('change', function () {
                discountedServiceTotal = Number.parseFloat(this.dataset.payableAmount || originalServiceTotal);
                refreshPaymentAmounts();
            });
        });

        const selectedCoupon = document.querySelector('.payment-coupon-radio:checked');
        if (selectedCoupon) {
            discountedServiceTotal = Number.parseFloat(selectedCoupon.dataset.payableAmount || originalServiceTotal);
        }

        // Select the required payment stage by default.
        selectPaymentOption('{{ $paymentType ?? 'advance' }}', document.querySelector('.payment-option'));
    </script>

    <!-- SweetAlert2 for notifications -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <style>
        .payment-option {
            cursor: pointer;
        }

        .payment-option div:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
        }

        .cursor-pointer {
            cursor: pointer;
        }
    </style>
@endsection
