@extends('layouts.app')

@section('content')
    @php
        $auditIsPaid = $case->audit_payment_status === 'paid';
        $auditOriginalFee = $auditIsPaid
            ? (float) ($case->audit_original_fee ?: $case->audit_fee)
            : (float) $case->audit_fee;
        $auditSelectedCoupon = isset($paymentCoupons) && $paymentCoupons->count() === 1 ? $paymentCoupons->first() : null;
        $auditDiscountAmount = $auditIsPaid
            ? (float) ($case->audit_discount_amount ?: 0)
            : ($auditSelectedCoupon ? $auditSelectedCoupon->discountAmountFor($auditOriginalFee) : 0);
        $auditPayableFee = $auditIsPaid
            ? (float) ($case->audit_paid_amount ?: max($auditOriginalFee - $auditDiscountAmount, 0))
            : max($auditOriginalFee - $auditDiscountAmount, 0);
        $auditSections = [
            [
                'title' => 'Registry Status Analysis',
                'items' => [
                    'Procedural review',
                    'Current bottleneck identification',
                ],
            ],
            [
                'title' => 'Filing Defect Review',
                'items' => [
                    'Applicant details review',
                    'Classification review',
                    'Specification review',
                    'Attorney filing quality review',
                ],
            ],
            [
                'title' => 'Recovery Strategy Report',
                'items' => [
                    'RTI requirement analysis',
                    'Amendment requirement analysis',
                    'Estimated next steps',
                    'Legal risk analysis',
                ],
            ],
            [
                'title' => 'Estimated Timeline',
                'items' => [
                    'Probable processing stage',
                    'Expected registry movement',
                ],
            ],
        ];
    @endphp

    <style>
        .audit-package-page {
            margin-top: -40px;
            padding: 34px 0 58px;
            background: linear-gradient(180deg, #f4fbfa 0%, #ffffff 72%);
            color: #1f2d3d;
        }

        .audit-package-shell {
            width: min(1160px, calc(100% - 40px));
            margin: 0 auto;
        }

        .audit-package-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 24px;
        }

        .audit-package-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #0a9a87;
            font-weight: 900;
            text-decoration: none;
        }

        .audit-package-back svg {
            width: 18px;
            height: 18px;
        }

        .audit-package-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 26px;
            align-items: start;
        }

        .audit-info-card,
        .audit-checkout-card {
            overflow: hidden;
            border: 1px solid #dfe9ee;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 16px 38px rgba(7, 29, 51, 0.08);
        }

        .audit-hero {
            padding: 24px 28px;
            background: linear-gradient(135deg, #071d33 0%, #008f7f 100%);
            color: #ffffff;
        }

        .audit-hero h1 {
            margin: 0 0 8px;
            color: #ffffff;
            font-size: clamp(1.35rem, 2vw, 1.75rem);
            font-weight: 950;
        }

        .audit-hero p {
            max-width: 760px;
            margin: 0;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
            font-weight: 700;
            line-height: 1.55;
        }

        .audit-info-body {
            padding: 24px 28px 30px;
        }

        .audit-purpose {
            padding: 18px;
            border: 1px solid #cfe4ef;
            border-radius: 12px;
            background: #f8fcff;
        }

        .audit-purpose span {
            display: block;
            margin-bottom: 8px;
            color: #0a9a87;
            font-size: 0.72rem;
            font-weight: 950;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .audit-purpose p {
            margin: 0;
            color: #1d3557;
            font-size: 0.92rem;
            font-weight: 800;
            line-height: 1.55;
        }

        .audit-section-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 20px;
        }

        .audit-section-card {
            min-height: 100%;
            padding: 18px;
            border: 1px solid #e3ebf6;
            border-radius: 12px;
            background: #ffffff;
        }

        .audit-section-card h2 {
            margin: 0 0 12px;
            color: #10233f;
            font-size: 0.82rem;
            font-weight: 950;
            line-height: 1.25;
        }

        .audit-section-card ul {
            display: grid;
            gap: 9px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .audit-section-card li {
            display: grid;
            grid-template-columns: 20px 1fr;
            gap: 8px;
            color: #44546a;
            font-size: 0.84rem;
            font-weight: 750;
            line-height: 1.35;
        }

        .audit-section-card li svg {
            width: 16px;
            height: 16px;
            color: #0a9a87;
            stroke-width: 2.8;
        }

        .audit-checkout-card {
            position: sticky;
            top: 22px;
        }

        .audit-checkout-head {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 22px 24px;
            background: #27466d;
            color: #ffffff;
        }

        .audit-checkout-icon {
            width: 58px;
            height: 58px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.14);
        }

        .audit-checkout-icon svg {
            width: 30px;
            height: 30px;
            stroke-width: 2.4;
        }

        .audit-checkout-head h2 {
            margin: 0;
            color: #ffffff;
            font-size: 0.98rem;
            font-weight: 950;
        }

        .audit-checkout-body {
            padding: 24px;
        }

        .audit-price-block {
            display: grid;
            gap: 5px;
        }

        .audit-original-price {
            display: inline-flex;
            justify-self: start;
            margin: 0;
            color: #64748b;
            font-size: 1rem;
            font-weight: 900;
            line-height: 1;
            text-decoration: line-through;
            text-decoration-thickness: 2px;
        }

        .audit-original-price.is-hidden,
        .audit-savings.is-hidden {
            display: none;
        }

        .audit-price {
            margin: 0;
            color: #10233f;
            font-size: 2rem;
            font-weight: 950;
            line-height: 1;
        }

        .audit-savings {
            margin: 0;
            color: #047857;
            font-size: 0.78rem;
            font-weight: 900;
            line-height: 1.25;
        }

        .audit-status {
            margin: 10px 0 0;
            color: #44546a;
            font-size: 0.9rem;
            font-weight: 900;
        }

        .audit-status span {
            color: #d97706;
        }

        .audit-status span.is-paid {
            color: #047857;
        }

        .audit-divider {
            margin: 22px 0;
            border-top: 2px dashed #d8e3ef;
        }

        .audit-summary-list {
            display: grid;
            gap: 12px;
            margin-bottom: 22px;
        }

        .audit-summary-item {
            display: grid;
            grid-template-columns: 24px 1fr;
            gap: 10px;
            align-items: center;
            color: #44546a;
            font-size: 0.88rem;
            font-weight: 900;
        }

        .audit-summary-item svg {
            width: 21px;
            height: 21px;
            color: #0a9a87;
            stroke-width: 2.6;
        }

        .audit-action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 9px 14px;
            border: 0;
            border-radius: 10px;
            background: linear-gradient(135deg, #0aa997 0%, #087f73 100%);
            color: #ffffff;
            font-size: 0.84rem;
            font-weight: 950;
        }

        .audit-action-button svg {
            width: 17px;
            height: 17px;
            stroke-width: 2.5;
        }

        .audit-action-button:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .audit-gate-note {
            margin: 12px 0 0;
            color: #64748b;
            font-size: 0.82rem;
            font-weight: 750;
            line-height: 1.4;
        }

        @media (max-width: 991.98px) {
            .audit-package-grid {
                grid-template-columns: 1fr;
            }

            .audit-checkout-card {
                position: static;
            }
        }

        @media (max-width: 640px) {
            .audit-package-shell {
                width: min(100% - 24px, 1160px);
            }

            .audit-package-top {
                align-items: flex-start;
                flex-direction: column;
            }

            .audit-hero,
            .audit-info-body,
            .audit-checkout-body,
            .audit-checkout-head {
                padding: 22px;
            }

            .audit-section-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="audit-package-page">
        <div class="audit-package-shell">
            <div class="audit-package-top">
                <a href="{{ route('stuck-trademark.show', $case) }}" class="audit-package-back">
                    <x-lucide-arrow-left /> Back to recovery case
                </a>
                <span class="badge bg-{{ $auditIsPaid ? 'success' : 'warning text-dark' }}">{{ ucfirst($case->audit_payment_status) }}</span>
            </div>

            <div class="audit-package-grid">
                <article class="audit-info-card">
                    <div class="audit-hero">
                        <h1>Audit Package</h1>
                        <p>Understand exactly what our legal audit covers before activating the recovery package for {{ $case->trademark_name }}.</p>
                    </div>
                    <div class="audit-info-body">
                        <div class="audit-purpose">
                            <span>Purpose</span>
                            <p>Diagnosis and procedural analysis of delayed trademark cases.</p>
                        </div>

                        <div class="audit-section-grid">
                            @foreach ($auditSections as $section)
                                <section class="audit-section-card">
                                    <h2>{{ $section['title'] }}</h2>
                                    <ul>
                                        @foreach ($section['items'] as $item)
                                            <li><x-lucide-check-circle /> <span>{{ $item }}</span></li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endforeach
                        </div>
                    </div>
                </article>

                <aside class="audit-checkout-card">
                    <div class="audit-checkout-head">
                        <span class="audit-checkout-icon"><x-lucide-pie-chart /></span>
                        <h2>Package Summary</h2>
                    </div>
                    <div class="audit-checkout-body">
                        <div class="audit-price-block" data-audit-price-block data-original-amount="{{ number_format($auditOriginalFee, 2, '.', '') }}">
                            <p class="audit-original-price {{ $auditDiscountAmount > 0 ? '' : 'is-hidden' }}" data-audit-original-price>
                                ₹{{ number_format($auditOriginalFee, 2) }}
                            </p>
                            <p class="audit-price" data-audit-payable-price>₹{{ number_format($auditPayableFee, 2) }}</p>
                            <p class="audit-savings {{ $auditDiscountAmount > 0 ? '' : 'is-hidden' }}" data-audit-savings>
                                You save ₹{{ number_format($auditDiscountAmount, 2) }}
                            </p>
                        </div>
                        <p class="audit-status">Status: <span class="{{ $auditIsPaid ? 'is-paid' : '' }}">{{ ucfirst($case->audit_payment_status) }}</span></p>

                        <div class="audit-divider"></div>

                        <div class="audit-summary-list">
                            <div class="audit-summary-item"><x-lucide-circle-check /> <span>Comprehensive case diagnosis</span></div>
                            <div class="audit-summary-item"><x-lucide-circle-check /> <span>Filing defects and bottleneck review</span></div>
                            <div class="audit-summary-item"><x-lucide-circle-check /> <span>Recovery strategy report</span></div>
                            <div class="audit-summary-item"><x-lucide-circle-check /> <span>Estimated timeline and next steps</span></div>
                        </div>

                        @include('partials.payment-coupons', [
                            'couponInputName' => 'audit_discount_coupon_id',
                            'paymentAmount' => $auditOriginalFee,
                        ])

                        @include('partials.government-fee-notice')

                        @if (! $auditIsPaid)
                            <button type="button" class="btn audit-action-button w-100" id="audit-pay-button" @disabled(! $canPurchaseAuditPackage)>
                                <x-lucide-credit-card /> Purchase Audit Package
                            </button>
                            @unless ($canPurchaseAuditPackage)
                                <p class="audit-gate-note">Audit package activation unlocks after all current uploaded documents are verified by the admin team.</p>
                            @endunless
                        @else
                            <a href="{{ route('stuck-trademark.audit-payment.invoice', $case) }}" target="_blank" class="btn btn-outline-success w-100">View Invoice</a>
                        @endif
                    </div>
                </aside>
            </div>
        </div>
    </div>

    @if (! $auditIsPaid)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const auditPayButton = document.getElementById('audit-pay-button');
                const auditPriceBlock = document.querySelector('[data-audit-price-block]');
                const auditOriginalPrice = document.querySelector('[data-audit-original-price]');
                const auditPayablePrice = document.querySelector('[data-audit-payable-price]');
                const auditSavings = document.querySelector('[data-audit-savings]');
                const auditCouponInputs = Array.from(document.querySelectorAll('input[name="audit_discount_coupon_id"]'));

                if (!auditPayButton) {
                    return;
                }

                const formatCurrency = (amount) => `£${Number(amount || 0).toLocaleString('en-GB', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                })}`;

                const syncAuditPrice = () => {
                    if (!auditPriceBlock || !auditOriginalPrice || !auditPayablePrice || !auditSavings) {
                        return;
                    }

                    const selectedCoupon = document.querySelector('input[name="audit_discount_coupon_id"]:checked');
                    const originalAmount = Number(auditPriceBlock.dataset.originalAmount || 0);
                    const discountAmount = Number(selectedCoupon?.dataset.discountAmount || 0);
                    const payableAmount = selectedCoupon?.dataset.payableAmount
                        ? Number(selectedCoupon.dataset.payableAmount)
                        : originalAmount;
                    const hasDiscount = discountAmount > 0 && payableAmount < originalAmount;

                    auditOriginalPrice.classList.toggle('is-hidden', !hasDiscount);
                    auditSavings.classList.toggle('is-hidden', !hasDiscount);
                    auditPayablePrice.textContent = formatCurrency(payableAmount);
                    auditSavings.textContent = `You save ${formatCurrency(discountAmount)}`;
                };

                auditCouponInputs.forEach((input) => {
                    input.addEventListener('change', syncAuditPrice);
                });
                syncAuditPrice();

                const showPaymentError = (message) => {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Payment Error',
                            text: message
                        });
                    } else {
                        alert(message);
                    }
                };

                const resetButton = () => {
                    auditPayButton.disabled = false;
                    window.LegalBruzButtonLoading?.reset(auditPayButton);
                };

                auditPayButton.addEventListener('click', () => {
                    const selectedCoupon = document.querySelector('input[name="audit_discount_coupon_id"]:checked');
                    auditPayButton.disabled = true;
                    window.LegalBruzButtonLoading?.set(auditPayButton, 'Creating Order...');

                    fetch('{{ route('stuck-trademark.audit-payment.create-order', $case) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            audit_discount_coupon_id: selectedCoupon?.value || null
                        })
                    })
                        .then((response) => response.json())
                        .then((data) => {
                            if (data.status !== 'success') {
                                throw new Error(data.message || 'Unable to create Revolut order.');
                            }

                            if (!data.checkout_url) throw new Error('Secure checkout URL was not returned.');
                            window.location.assign(data.checkout_url);
                        })
                        .catch((error) => {
                            showPaymentError(error.message);
                            resetButton();
                        });
                });
            });
        </script>
    @endif
@endsection
