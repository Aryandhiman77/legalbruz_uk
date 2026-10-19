@extends('layouts.app')

@section('content')
    <style>
        .oppose-shell{max-width:1040px;margin:-18px auto 32px;padding:0 18px;color:#26364f}.oppose-card{background:#fff;border:1px solid #dfe8f4;border-radius:10px;box-shadow:0 16px 34px rgba(8,36,90,.08);overflow:hidden}.oppose-head{padding:22px 26px;background:linear-gradient(135deg,#0f7b31,#1f9a43);color:#fff}.oppose-head h1{margin:0;color:#fff;font-size:1.65rem}.oppose-head p{margin:6px 0 0;color:rgba(255,255,255,.86)}.oppose-body{padding:24px}.oppose-warning{padding:12px 14px;border:1px solid #f3d28a;border-radius:7px;background:#fff8e5;color:#62430c;font-size:.92rem;margin-bottom:18px}.oppose-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.oppose-grid .full{grid-column:1/-1}.oppose-label{display:block;margin-bottom:7px;color:#10285c;font-weight:800;font-size:.9rem}.oppose-input{width:100%;min-height:42px;border:1px solid #cad6e8;border-radius:7px;padding:9px 11px;color:#25304a}.oppose-input:focus{outline:0;border-color:#1f9a43;box-shadow:0 0 0 3px rgba(31,154,67,.12)}.oppose-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:20px}.oppose-btn{min-height:42px;border:0;border-radius:7px;padding:0 20px;background:#1f9a43;color:#fff;font-weight:800;text-decoration:none;display:inline-flex;align-items:center}.oppose-btn:hover{color:#fff;background:#188338}@media(max-width:767px){.oppose-grid{grid-template-columns:1fr}.oppose-body{padding:20px}.oppose-head{padding:20px}}
    </style>

    <div class="oppose-shell">
        <div class="oppose-card">
            <div class="oppose-head">
                <h1>Oppose a Trademark</h1>
                <p>Step 1: Trademark Conflict Details</p>
            </div>
            <form class="oppose-body" method="POST" action="{{ route('trademark-opposition.oppose.store') }}">
                @csrf
                <div class="oppose-warning">
                    Trademark opposition is filed against another trademark after it is advertised in the Trademark Journal. This is different from Trademark Objection Reply, which is raised by the Trademark Examiner during examination.
                </div>

                <div class="oppose-grid">
                    <div>
                        <label class="oppose-label">Trademark You Own</label>
                        <input class="oppose-input @error('trademark_you_own') is-invalid @enderror" name="trademark_you_own" value="{{ old('trademark_you_own') }}" required>
                        @error('trademark_you_own')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="oppose-label">Trademark You Want To Oppose</label>
                        <input class="oppose-input @error('trademark_to_oppose') is-invalid @enderror" name="trademark_to_oppose" value="{{ old('trademark_to_oppose') }}" required>
                        @error('trademark_to_oppose')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="oppose-label">Application Number of Trademark You Want To Oppose</label>
                        <input class="oppose-input @error('opposed_application_number') is-invalid @enderror" name="opposed_application_number" value="{{ old('opposed_application_number') }}" required>
                        @error('opposed_application_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="oppose-label">Trademark Class</label>
                        <input class="oppose-input @error('trademark_class') is-invalid @enderror" name="trademark_class" value="{{ old('trademark_class') }}" required>
                        @error('trademark_class')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="full">
                        <label class="oppose-label">Why do you believe it conflicts?</label>
                        <textarea class="oppose-input @error('conflict_reason') is-invalid @enderror" name="conflict_reason" rows="4" required>{{ old('conflict_reason') }}</textarea>
                        @error('conflict_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="oppose-label">Applicant Name of Opposed Trademark, if known</label>
                        <input class="oppose-input" name="opposed_applicant_name" value="{{ old('opposed_applicant_name') }}">
                    </div>
                    <div>
                        <label class="oppose-label">Your Name / Business Name</label>
                        <input class="oppose-input @error('user_business_name') is-invalid @enderror" name="user_business_name" value="{{ old('user_business_name', auth()->user()->name) }}" required>
                        @error('user_business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="oppose-label" for="mobile_number">Mobile Number</label>
                        <input class="oppose-input @error('mobile_number') is-invalid @enderror" id="mobile_number" type="tel" name="mobile_number" value="{{ old('mobile_number', \App\Support\UkMobileNumber::normalizeOrNull(auth()->user()->mobile)) }}" autocomplete="tel" inputmode="tel" maxlength="20" pattern="(?:\+44 ?7|07)(?:[0-9] ?){9}" placeholder="07123 456789" title="Enter a valid UK mobile number" required>
                        <small>UK mobile numbers only: use 07 or +44 7.</small>
                        @error('mobile_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="oppose-label">Email Address</label>
                        <input class="oppose-input @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="oppose-actions">
                    <a class="oppose-btn" style="background:#6b7280" href="{{ route('trademark.opposition-management') }}">Back</a>
                    <button class="oppose-btn" type="submit">Create Case</button>
                </div>
            </form>
        </div>
    </div>
@endsection
