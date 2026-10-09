@extends('layouts.app')
@section('title', 'Book a Consultation Call | Legal Bruz')
@section('meta_description', 'Book a paid consultation call with Legal Bruz for guidance on your UK trade mark matter.')
@section('canonical_url', route('book-call.create'))

@section('content')
    @include('pages.partials.styles')
    <main class="call-page">
        <div class="call-shell">
            <header class="call-hero">
                <div>
                    <span>PAID CONSULTATION</span>
                    <h1>Book a call with our team</h1>
                    <p>Tell us what you would like to discuss and choose a preferred date and time window. We will confirm the exact appointment after payment.</p>
                </div>
                <aside class="call-fee-card">
                    <small>Consultation fee</small>
                    <strong>£{{ number_format($consultationFee, 2) }}</strong>
                    <span>Secure online payment</span>
                </aside>
            </header>

            @if ($errors->any())
                <div class="call-alert" role="alert"><i class="bi bi-exclamation-circle"></i><div><strong>Please check the highlighted fields.</strong><span>Your information has been kept.</span></div></div>
            @endif

            <div class="call-layout">
                <section class="call-form-card">
                    <div class="call-heading"><div><span>YOUR DETAILS</span><h2>Consultation request</h2><p>Fields marked with * are required.</p></div><i class="bi bi-calendar2-check"></i></div>
                    <form method="POST" action="{{ route('book-call.store') }}" class="call-form">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6"><label for="call_name">Full Name *</label><input id="call_name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" maxlength="120" autocomplete="name" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label for="call_email">Email Address *</label><input type="email" id="call_email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" maxlength="190" autocomplete="email" required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label for="call_phone">Phone Number *</label><input type="tel" id="call_phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" maxlength="30" autocomplete="tel" required>@error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label for="call_business">Business Name <span>(Optional)</span></label><input id="call_business" name="business_name" class="form-control @error('business_name') is-invalid @enderror" value="{{ old('business_name') }}" maxlength="180" autocomplete="organization">@error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-12"><label for="call_topic">What would you like to discuss? *</label><select id="call_topic" name="service_topic" class="form-select @error('service_topic') is-invalid @enderror" required><option value="">Select a topic</option>@foreach($topics as $value => $label)<option value="{{ $value }}" @selected(old('service_topic') === $value)>{{ $label }}</option>@endforeach</select>@error('service_topic')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label for="call_date">Preferred Date *</label><input type="date" id="call_date" name="preferred_date" class="form-control @error('preferred_date') is-invalid @enderror" value="{{ old('preferred_date') }}" min="{{ now()->toDateString() }}" required>@error('preferred_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label for="call_time">Preferred Time *</label><select id="call_time" name="preferred_time" class="form-select @error('preferred_time') is-invalid @enderror" required><option value="">Select a time window</option>@foreach($timeSlots as $value => $label)<option value="{{ $value }}" @selected(old('preferred_time') === $value)>{{ $label }}</option>@endforeach</select>@error('preferred_time')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-12"><label for="call_message">Briefly describe your matter *</label><textarea id="call_message" name="message" class="form-control @error('message') is-invalid @enderror" rows="5" minlength="10" maxlength="5000" required>{{ old('message') }}</textarea>@error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="d-none" aria-hidden="true"><label for="call_website">Website</label><input id="call_website" name="website" tabindex="-1" autocomplete="off"></div>
                            <div class="col-12 call-submit-row"><button type="submit">Continue to payment · £{{ number_format($consultationFee, 2) }} <i class="bi bi-arrow-right"></i></button><small>By continuing, you agree to our <a href="{{ route('terms') }}">Terms</a>, <a href="{{ route('privacy') }}">Privacy Policy</a> and <a href="{{ route('refund') }}">Refund Policy</a>.</small></div>
                        </div>
                    </form>
                </section>

                <aside class="call-info-card">
                    <span>WHAT HAPPENS NEXT</span>
                    <h2>A focused first conversation</h2>
                    <ol><li><b>1</b><div><strong>Submit your details</strong><p>Tell us about the issue and your preferred availability.</p></div></li><li><b>2</b><div><strong>Pay securely</strong><p>Complete the consultation payment through Razorpay.</p></div></li><li><b>3</b><div><strong>We confirm the slot</strong><p>Our team will contact you to confirm the exact call time.</p></div></li></ol>
                    <div class="call-note"><i class="bi bi-info-circle"></i><p>Your preferred date and window are a request, not a guaranteed appointment. We will agree the final slot with you.</p></div>
                </aside>
            </div>
        </div>
    </main>

    <style>
        .call-page{min-height:100vh;padding:54px 20px 80px;background:#f3f8fa;color:#102a4c}.call-shell{width:min(1180px,100%);margin:auto}.call-hero{display:flex;align-items:center;justify-content:space-between;gap:36px;padding:38px;border-radius:24px;background:radial-gradient(circle at 85% 0,rgba(102,229,207,.24),transparent 32%),linear-gradient(125deg,#071f48,#0d6968);color:#fff;box-shadow:0 22px 50px rgba(7,31,72,.13)}.call-hero>div{max-width:760px}.call-hero span,.call-heading span,.call-info-card>span{font-size:.7rem;font-weight:900;letter-spacing:.14em;color:#78dfd1}.call-hero h1{margin:10px 0;color:#fff;font-size:clamp(2rem,4vw,3.3rem);letter-spacing:-.045em}.call-hero p{margin:0;color:rgba(255,255,255,.78);line-height:1.75}.call-fee-card{flex:0 0 220px;padding:22px;border:1px solid rgba(255,255,255,.2);border-radius:18px;background:rgba(255,255,255,.1);text-align:center}.call-fee-card small,.call-fee-card strong,.call-fee-card span{display:block}.call-fee-card small{color:rgba(255,255,255,.7)}.call-fee-card strong{margin:4px 0;color:#fff;font-size:2.35rem}.call-fee-card span{color:#8de8dc;letter-spacing:0;font-size:.75rem}.call-alert{display:flex;gap:12px;margin-top:20px;padding:15px;border:1px solid #efb1ad;border-radius:12px;background:#fff1f0;color:#9b2019}.call-alert div{display:grid}.call-layout{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(300px,.75fr);gap:22px;margin-top:22px}.call-form-card,.call-info-card{border:1px solid #dfe8f0;border-radius:20px;background:#fff;box-shadow:0 15px 36px rgba(7,31,72,.06)}.call-form-card{padding:28px}.call-heading{display:flex;justify-content:space-between;gap:20px;padding-bottom:20px;border-bottom:1px solid #e7edf2}.call-heading span,.call-info-card>span{color:#0e8f82}.call-heading h2,.call-info-card h2{margin:5px 0;color:#102a4c}.call-heading p{margin:0;color:#718096}.call-heading>i{display:grid;place-items:center;width:50px;height:50px;border-radius:14px;background:#e8f7f4;color:#0c8d80;font-size:1.25rem}.call-form{margin-top:22px}.call-form label{display:block;margin-bottom:7px;color:#173657;font-size:.84rem;font-weight:800}.call-form label span{color:#7b8999;font-size:.72rem;font-weight:600}.call-form .form-control,.call-form .form-select{min-height:50px;border-color:#cad9e4;border-radius:10px}.call-form textarea.form-control{min-height:125px}.call-form .form-control:focus,.call-form .form-select:focus{border-color:#159b8d;box-shadow:0 0 0 .2rem rgba(21,155,141,.13)}.call-submit-row{display:grid;gap:10px}.call-submit-row button{min-height:52px;border:0;border-radius:11px;background:#129486;color:#fff;font-weight:900;box-shadow:0 10px 22px rgba(18,148,134,.2)}.call-submit-row button:hover{background:#0d7d72}.call-submit-row small{color:#748196;line-height:1.5}.call-submit-row a{color:#0d7d72}.call-info-card{align-self:start;padding:28px}.call-info-card ol{display:grid;gap:20px;margin:25px 0;padding:0;list-style:none}.call-info-card li{display:flex;gap:13px}.call-info-card li>b{display:grid;place-items:center;flex:0 0 38px;height:38px;border-radius:11px;background:#e8f7f4;color:#0c8d80}.call-info-card li strong{color:#173657}.call-info-card li p{margin:4px 0 0;color:#718096;font-size:.85rem;line-height:1.55}.call-note{display:flex;gap:10px;padding:14px;border-radius:12px;background:#fff8e6;color:#73510d}.call-note p{margin:0;font-size:.8rem;line-height:1.55}@media(max-width:900px){.call-layout{grid-template-columns:1fr}.call-hero{align-items:flex-start}.call-fee-card{flex-basis:190px}}@media(max-width:650px){.call-page{padding:28px 14px 55px}.call-hero{flex-direction:column;padding:27px 22px}.call-fee-card{width:100%;flex-basis:auto}.call-form-card,.call-info-card{padding:21px}}
    </style>
@endsection
