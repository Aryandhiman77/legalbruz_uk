@extends('layouts.app')
@section('title', 'Consultation Payment | Legal Bruz')
@section('meta_description', 'Complete payment for your Legal Bruz consultation call.')

@section('content')
    <main class="call-payment-page">
        <section class="call-payment-card">
            @if ($booking->payment_status === 'paid')
                <div class="call-payment-success"><i class="bi bi-check-circle-fill"></i><span>Payment completed</span></div>
                <h1>Your consultation is booked</h1>
                <p>We have received your payment and will contact you to confirm the exact appointment time.</p>
                <a href="{{ route('book-call.success', $booking) }}" class="call-pay-button">View confirmation</a>
            @else
                <span class="call-payment-kicker">SECURE CHECKOUT</span>
                <h1>Complete your consultation booking</h1>
                <p class="call-payment-copy">Review the request below and pay the fixed consultation fee.</p>
                <dl><div><dt>Name</dt><dd>{{ $booking->name }}</dd></div><div><dt>Topic</dt><dd>{{ $topic }}</dd></div><div><dt>Preferred date</dt><dd>{{ $booking->preferred_date->format('d M Y') }}</dd></div><div><dt>Preferred time</dt><dd>{{ $timeSlot }}</dd></div></dl>
                <div class="call-payment-total"><span>Total due</span><strong>£{{ number_format((float) $booking->amount, 2) }}</strong></div>
                <div class="call-payment-error" data-payment-error hidden></div>
                <button type="button" class="call-pay-button" data-pay-consultation>Pay £{{ number_format((float) $booking->amount, 2) }} securely <i class="bi bi-lock-fill"></i></button>
                <a class="call-payment-back" href="{{ route('book-call.create') }}">Start a new request</a>
            @endif
        </section>
    </main>

    <style>
        .call-payment-page{display:grid;place-items:center;min-height:78vh;padding:48px 18px;background:#f3f8fa;color:#102a4c}.call-payment-card{width:min(660px,100%);padding:34px;border:1px solid #dce7ef;border-radius:22px;background:#fff;box-shadow:0 24px 60px rgba(7,31,72,.1)}.call-payment-kicker{color:#0d8d80;font-size:.7rem;font-weight:900;letter-spacing:.14em}.call-payment-card h1{margin:8px 0;color:#102a4c;font-size:clamp(1.7rem,4vw,2.5rem)}.call-payment-copy,.call-payment-card>p{color:#6d7d91;line-height:1.65}.call-payment-card dl{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:24px 0}.call-payment-card dl div{padding:14px;border-radius:12px;background:#f6f9fb}.call-payment-card dt{color:#748196;font-size:.68rem;text-transform:uppercase;letter-spacing:.06em}.call-payment-card dd{margin:5px 0 0;color:#173657;font-weight:800}.call-payment-total{display:flex;align-items:center;justify-content:space-between;padding:18px;border-radius:13px;background:#eaf7f5}.call-payment-total span{font-weight:800}.call-payment-total strong{color:#087d72;font-size:1.8rem}.call-pay-button{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;min-height:52px;margin-top:18px;border:0;border-radius:11px;background:#129486;color:#fff;font-weight:900;text-decoration:none}.call-pay-button:hover{background:#0c7c71;color:#fff}.call-payment-back{display:block;margin-top:15px;color:#60748b;text-align:center}.call-payment-error{margin-top:14px;padding:12px;border-radius:9px;background:#fff1f0;color:#a1261e}.call-payment-success{display:flex;align-items:center;gap:9px;color:#0b876e;font-weight:900}.call-payment-success i{font-size:1.4rem}@media(max-width:560px){.call-payment-card{padding:24px 18px}.call-payment-card dl{grid-template-columns:1fr}}
    </style>

    @if ($booking->payment_status !== 'paid')
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script>
            (() => {
                const button = document.querySelector('[data-pay-consultation]');
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
                        const orderResponse = await fetch(@json(route('book-call.payment.order', $booking)), {
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
                                const verifyResponse = await fetch(@json(route('book-call.payment.verify', $booking)), {
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
