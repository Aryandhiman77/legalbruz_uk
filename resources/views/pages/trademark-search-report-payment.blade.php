@extends('layouts.app')
@section('title', 'Trademark Search Report Payment | Legal Bruz')
@section('meta_description', 'Complete secure payment for your Legal Bruz trademark search report.')

@section('content')
    <main class="report-payment-page">
        <section class="report-payment-card">
            @if ($reportRequest->payment_status === 'paid')
                <div class="report-payment-success"><i class="bi bi-check-circle-fill"></i><span>Payment completed</span></div>
                <h1>Your search report request is active</h1>
                <p>We have received your payment and the report workflow can now begin.</p>
                <a href="{{ route('trademark-search-report.success', $reportRequest) }}" class="report-pay-button">View confirmation</a>
            @else
                <span class="report-payment-kicker">SECURE CHECKOUT</span>
                <h1>Start your trademark search report</h1>
                <p class="report-payment-copy">Review your brand details and pay the fixed report fee.</p>
                <dl>
                    <div><dt>Name</dt><dd>{{ $reportRequest->name }}</dd></div>
                    <div><dt>Brand</dt><dd>{{ $reportRequest->brand_name }}</dd></div>
                    <div class="wide"><dt>Goods, services or business activity</dt><dd>{{ $reportRequest->business_activity }}</dd></div>
                </dl>
                <div class="report-payment-total"><span>Total due</span><strong>£{{ number_format((float) $reportRequest->amount, 2) }}</strong></div>
                <div class="report-payment-error" data-payment-error hidden></div>
                <button type="button" class="report-pay-button" data-pay-report>Pay £{{ number_format((float) $reportRequest->amount, 2) }} securely <i class="bi bi-lock-fill"></i></button>
                <a class="report-payment-back" href="{{ route('trademark-search-report.create') }}">Start a new request</a>
            @endif
        </section>
    </main>

    <style>
        .report-payment-page{display:grid;place-items:center;min-height:78vh;padding:48px 18px;background:#f3f8fa;color:#102a4c}.report-payment-card{width:min(700px,100%);padding:34px;border:1px solid #dce7ef;border-radius:22px;background:#fff;box-shadow:0 24px 60px rgba(7,31,72,.1)}.report-payment-kicker{color:#0d8d80;font-size:.7rem;font-weight:900;letter-spacing:.14em}.report-payment-card h1{margin:8px 0;color:#102a4c;font-size:clamp(1.7rem,4vw,2.5rem)}.report-payment-copy,.report-payment-card>p{color:#6d7d91;line-height:1.65}.report-payment-card dl{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:24px 0}.report-payment-card dl div{padding:14px;border-radius:12px;background:#f6f9fb}.report-payment-card dl .wide{grid-column:1/-1}.report-payment-card dt{color:#748196;font-size:.68rem;text-transform:uppercase;letter-spacing:.06em}.report-payment-card dd{margin:5px 0 0;color:#173657;font-weight:800;white-space:pre-wrap}.report-payment-total{display:flex;align-items:center;justify-content:space-between;padding:18px;border-radius:13px;background:#eaf7f5}.report-payment-total span{font-weight:800}.report-payment-total strong{color:#087d72;font-size:1.8rem}.report-pay-button{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;min-height:52px;margin-top:18px;border:0;border-radius:11px;background:#129486;color:#fff;font-weight:900;text-decoration:none}.report-pay-button:hover{background:#0c7c71;color:#fff}.report-payment-back{display:block;margin-top:15px;color:#60748b;text-align:center}.report-payment-error{margin-top:14px;padding:12px;border-radius:9px;background:#fff1f0;color:#a1261e}.report-payment-success{display:flex;align-items:center;gap:9px;color:#0b876e;font-weight:900}.report-payment-success i{font-size:1.4rem}@media(max-width:560px){.report-payment-card{padding:24px 18px}.report-payment-card dl{grid-template-columns:1fr}}
    </style>

    @if ($reportRequest->payment_status !== 'paid')
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script>
            (() => {
                const button = document.querySelector('[data-pay-report]');
                const errorBox = document.querySelector('[data-payment-error]');
                if (!button || !errorBox) return;

                const showError = message => {
                    errorBox.textContent = message;
                    errorBox.hidden = false;
                };

                button.addEventListener('click', async () => {
                    errorBox.hidden = true;
                    if (!window.Razorpay) return showError('Secure checkout could not be loaded. Please refresh and try again.');
                    const original = button.innerHTML;
                    button.disabled = true;
                    button.textContent = 'Preparing secure payment…';

                    try {
                        const orderResponse = await fetch(@json(route('trademark-search-report.payment.order', $reportRequest)), {
                            method: 'POST',
                            headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':@json(csrf_token())},
                        });
                        const order = await orderResponse.json();
                        if (!orderResponse.ok || order.status !== 'success') throw new Error(order.message || 'Payment could not be started.');

                        const checkout = new Razorpay({
                            key: order.key,
                            amount: order.amount,
                            currency: order.currency,
                            name: 'Legal Bruz Ltd.',
                            description: order.description,
                            order_id: order.order_id,
                            prefill: {name: order.name, email: order.email, contact: order.phone},
                            theme: {color: '#129486'},
                            handler: async response => {
                                button.textContent = 'Verifying payment…';
                                const verifyResponse = await fetch(@json(route('trademark-search-report.payment.verify', $reportRequest)), {
                                    method: 'POST',
                                    headers: {'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':@json(csrf_token())},
                                    body: JSON.stringify(response),
                                });
                                const verified = await verifyResponse.json();
                                if (!verifyResponse.ok || verified.status !== 'success') throw new Error(verified.message || 'Payment verification failed.');
                                window.location.href = verified.redirect_url;
                            },
                            modal: {ondismiss: () => {button.disabled = false; button.innerHTML = original;}},
                        });
                        checkout.on('payment.failed', response => showError(response.error?.description || 'Payment failed. Please try again.'));
                        checkout.open();
                        button.disabled = false;
                        button.innerHTML = original;
                    } catch (error) {
                        showError(error.message || 'Payment could not be processed.');
                        button.disabled = false;
                        button.innerHTML = original;
                    }
                });
            })();
        </script>
    @endif
@endsection
