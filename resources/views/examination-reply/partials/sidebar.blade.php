<div class="flowb-sidebar-stack">
    <aside class="flowb-card flowb-sidebar">
        <h2>Case Summary</h2>
        <div class="flowb-side-body">
            <div class="flowb-field"><span>Reply Deadline</span><strong><span class="flowb-badge flowb-deadline {{ $deadlineClass }}">{{ $case->reply_deadline->format('d M Y') }} · {{ $case->deadline_status_label }}</span></strong></div>
            <div class="flowb-field"><span>Current Status</span><strong>{{ $case->current_status }}</strong></div>
            <div class="flowb-field"><span>Trademark Class</span><strong>{{ $case->trademark_class }}</strong></div>
            <div class="flowb-field"><span>Payment</span><strong>{{ ucfirst($case->payment_status) }}</strong></div>
            <div class="flowb-field"><span>Filed through LegalBRUZ</span><strong>{{ $case->filed_through_legalbruz ? 'Yes' : 'No' }}</strong></div>
            @if ($case->risk_level)
                <div class="risk-card">
                    <strong>{{ $case->risk_level }}</strong>
                    <p>{{ $case->client_visible_risk_note ?: $case->risk_reason }}</p>
                    <p>Filing a reply does not guarantee acceptance. Final decision rests with the Trademark Registry.</p>
                </div>
            @endif
        </div>
        @if ($objectionDetailRows->isNotEmpty())
            <section class="flowb-objection-details">
                <h4>Objection Details</h4>
                <div class="flowb-objection-grid">
                    @foreach($objectionDetailRows as $label => $value)
                        <div class="flowb-objection-item">
                            <span>{{ $label }}</span>
                            <strong>{{ $value }}</strong>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </aside>

    @if ($case->package_price)
        <aside class="flowb-card flowb-pricing" aria-labelledby="pricing-summary-title">
            <h2 id="pricing-summary-title"><i class="bi bi-credit-card-2-front"></i> Pricing &amp; Payment</h2>
            <div class="flowb-price-body">
                <div class="flowb-price-package">
                    <span class="flowb-price-label">Selected Package</span>
                    <strong>{{ $case->package_type ?: 'Trademark Objection Reply' }}</strong>
                    @if ($objectionReplyDiscountAmount > 0)
                        <span class="flowb-price-original">£{{ number_format($objectionReplyOriginalAmount, 2) }}</span>
                    @endif
                    <span class="flowb-price-amount">£{{ number_format($objectionReplyPayableAmount, 2) }}</span>
                    @if ($objectionReplyDiscountAmount > 0)
                        <p class="flowb-price-description">Discount{{ $case->payment_status === 'paid' && $case->coupon_label ? ' (' . $case->coupon_label . ')' : '' }}: −£{{ number_format($objectionReplyDiscountAmount, 2) }}</p>
                    @endif
                    @if ($case->package_description)
                        <p class="flowb-price-description">{{ $case->package_description }}</p>
                    @endif
                </div>

                @if (!empty($case->included_services))
                    <span class="flowb-price-label mt-3">What's included</span>
                    <ul class="flowb-price-list">
                        @foreach ($case->included_services as $service)
                            <li><i class="bi bi-check-circle-fill"></i><span>{{ $service }}</span></li>
                        @endforeach
                    </ul>
                @endif

                @if (!empty($case->add_ons))
                    <span class="flowb-price-label mt-3">Add-ons</span>
                    <ul class="flowb-price-list">
                        @foreach ($case->add_ons as $addOn)
                            <li><i class="bi bi-plus-circle-fill"></i><span>{{ is_array($addOn) ? ($addOn['name'] ?? $addOn['label'] ?? implode(' · ', $addOn)) : $addOn }}</span></li>
                        @endforeach
                    </ul>
                @endif

                <div class="flowb-price-status {{ $case->payment_status === 'paid' ? 'is-paid' : '' }}">
                    <span class="flowb-price-label">Payment status</span>
                    <strong><i class="bi {{ $case->payment_status === 'paid' ? 'bi-check-circle-fill' : 'bi-clock' }}"></i> {{ $case->payment_status }}</strong>
                </div>

                @if ($case->payment_status === 'paid' && $case->payment_reference && $case->transaction_id && $case->paid_at)
                    <a class="flowb-btn flowb-invoice-btn" href="{{ route('examination-reply.payment.invoice', $case) }}" target="_blank" rel="noopener">
                        <i class="bi bi-receipt"></i> View Invoice
                    </a>
                @endif
            </div>
        </aside>
    @endif
</div>
