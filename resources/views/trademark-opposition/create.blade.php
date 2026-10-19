@extends('layouts.app')

@section('content')
    <style>
        .opp-form-shell{max-width:980px;margin:-18px auto 30px;padding:0 18px}.opp-panel{background:#fff;border:1px solid #dfe8f4;border-radius:10px;box-shadow:0 16px 34px rgba(8,36,90,.08);overflow:hidden}.opp-panel-head{padding:22px 26px;background:linear-gradient(135deg,#08245a,#123a82);color:#fff}.opp-panel-head h1{margin:0;color:#fff;font-size:1.7rem}.opp-panel-head p{margin:6px 0 0;color:rgba(255,255,255,.82)}.opp-panel-body{padding:26px}.opp-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.opp-grid .full{grid-column:1/-1}.opp-label{display:block;margin-bottom:7px;color:#10285c;font-weight:800;font-size:.92rem}.opp-input{width:100%;min-height:44px;border:1px solid #cad6e8;border-radius:7px;padding:9px 12px;color:#25304a}.opp-input:focus{outline:0;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}.opp-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:22px}.opp-btn{min-height:44px;border:0;border-radius:7px;padding:0 22px;background:#2563eb;color:#fff;font-weight:900;text-decoration:none;display:inline-flex;align-items:center}.opp-btn:hover{color:#fff;background:#1d4ed8}.opp-note{margin-top:18px;padding:13px 15px;border:1px solid #f3d28a;border-radius:7px;background:#fff8e5;color:#62430c;font-size:.92rem}@media(max-width:767px){.opp-grid{grid-template-columns:1fr}.opp-panel-body{padding:20px}.opp-panel-head{padding:20px}}
    </style>

    <div class="opp-form-shell">
        <div class="opp-panel">
            <div class="opp-panel-head">
                <h1>Defend My Trademark</h1>
                <p>Step 1: Basic Information for Notice of Opposition defence</p>
            </div>
            <form class="opp-panel-body" method="POST" action="{{ route('trademark-opposition.store') }}">
                @csrf
                <div class="opp-grid">
                    <div>
                        <label class="opp-label" for="applicant_name">Applicant Name</label>
                        <input class="opp-input @error('applicant_name') is-invalid @enderror" id="applicant_name" name="applicant_name" value="{{ old('applicant_name', request('applicant_name', auth()->user()->name)) }}" required>
                        @error('applicant_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="opp-label" for="trademark_name">Trademark Name</label>
                        <input class="opp-input @error('trademark_name') is-invalid @enderror" id="trademark_name" name="trademark_name" value="{{ old('trademark_name', request('trademark_name')) }}" required>
                        @error('trademark_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="opp-label" for="application_number">Application Number</label>
                        <input class="opp-input @error('application_number') is-invalid @enderror" id="application_number" name="application_number" value="{{ old('application_number', request('application_number')) }}" required>
                        @error('application_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="opp-label" for="trademark_class">Trademark Class</label>
                        <input class="opp-input @error('trademark_class') is-invalid @enderror" id="trademark_class" name="trademark_class" value="{{ old('trademark_class', request('trademark_class')) }}" required>
                        @error('trademark_class')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="opp-label" for="mobile_number">Mobile Number</label>
                        <input class="opp-input @error('mobile_number') is-invalid @enderror" id="mobile_number" type="tel" name="mobile_number" value="{{ old('mobile_number', request('mobile_number', \App\Support\UkMobileNumber::normalizeOrNull(auth()->user()->mobile))) }}" autocomplete="tel" inputmode="tel" maxlength="20" pattern="(?:\+44 ?7|07)(?:[0-9] ?){9}" placeholder="07123 456789" title="Enter a valid UK mobile number" required>
                        <small>UK mobile numbers only: use 07 or +44 7.</small>
                        @error('mobile_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="opp-label" for="email">Email Address</label>
                        <input class="opp-input @error('email') is-invalid @enderror" id="email" type="email" name="email" value="{{ old('email', request('email', auth()->user()->email)) }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="full">
                        <label class="opp-label" for="notice_receipt_date">Date of Receipt of Notice of Opposition</label>
                        <input class="opp-input @error('notice_receipt_date') is-invalid @enderror" id="notice_receipt_date" type="date" name="notice_receipt_date" value="{{ old('notice_receipt_date', request('notice_receipt_date')) }}" required>
                        @error('notice_receipt_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="opp-note">
                    <strong>NOTE:</strong> Failure to file a Counter Statement within the prescribed period may result in abandonment of the application.
                </div>
                <div class="opp-actions">
                    <a class="opp-btn" style="background:#6b7280" href="{{ route('trademark.opposition-management') }}">Back</a>
                    <button class="opp-btn" type="submit">Create Case</button>
                </div>
            </form>
        </div>
    </div>
@endsection
