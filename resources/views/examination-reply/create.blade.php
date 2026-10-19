@extends('layouts.app')

@section('content')
    <style>
        .err-form{max-width:980px;margin:-10px auto 36px;padding:0 18px;color:#22324a}.err-card{background:#fff;border:1px solid #dfe8f4;border-radius:9px;box-shadow:0 14px 34px rgba(8,36,90,.08);overflow:hidden}.err-head{background:#203e68;color:#fff;padding:24px}.err-head h1{color:#fff!important;font-size:1.6rem;margin:0;font-weight:900}.err-head p{margin:8px 0 0;color:#d9e7ff;font-weight:650}.err-body{padding:24px}.err-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.err-field label{display:block;font-weight:900;color:#263c5c;margin-bottom:7px}.err-field input,.err-field select{width:100%;min-height:44px;border:1px solid #d9e0ea;border-radius:7px;padding:9px 11px}.err-field small{display:block;margin-top:6px;color:#66758b;font-weight:650}.err-full{grid-column:1/-1}.err-warning{border-left:4px solid #f59e0b;background:#fff7ed;border-radius:8px;padding:14px;margin:18px 0;color:#7c3f00;font-weight:750}.err-btn{border:0;border-radius:7px;background:#2a9d8f;color:#fff;font-weight:900;min-height:46px;padding:0 18px}.err-back{display:inline-flex;align-items:center;min-height:46px;border-radius:7px;background:#e5eaf2;color:#203e68;text-decoration:none;font-weight:900;padding:0 16px;margin-left:8px}@media(max-width:760px){.err-grid{grid-template-columns:1fr}.err-body,.err-head{padding:18px}}
    </style>

    <div class="err-form">
        <form class="err-card" method="POST" action="{{ route('examination-reply.store') }}" enctype="multipart/form-data">
            @csrf
            <header class="err-head">
                <h1>Start Trademark Objection Reply</h1>
                <p>Upload the Examination Report so our legal team can review the objection and reply deadline.</p>
            </header>
            <div class="err-body">
                @if ($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
                <div class="err-grid">
                    <div class="err-field"><label>Applicant Name</label><input name="applicant_name" value="{{ old('applicant_name') }}" required></div>
                    <div class="err-field"><label>Brand / Trademark Name</label><input name="trademark_name" value="{{ old('trademark_name') }}" required></div>
                    <div class="err-field"><label>Application Number</label><input name="application_number" value="{{ old('application_number') }}" required></div>
                    <div class="err-field"><label>Trademark Class</label><input name="trademark_class" value="{{ old('trademark_class') }}" required></div>
                    <div class="err-field"><label>Filing Date</label><input type="date" name="application_filing_date" value="{{ old('application_filing_date') }}" required></div>
                    <div class="err-field">
                        <label>Current Status</label>
                        <select name="current_status" required>
                            @foreach(['Objected','Awaiting reply to examination report','Hearing required','Not sure'] as $status)
                                <option value="{{ $status }}" @selected(old('current_status') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="err-field">
                        <label>Was the trademark filed through LegalBRUZ?</label>
                        <select name="filed_through_legalbruz" required>
                            <option value="1" @selected(old('filed_through_legalbruz') === '1')>Yes</option>
                            <option value="0" @selected(old('filed_through_legalbruz') === '0')>No</option>
                        </select>
                    </div>
                    <div class="err-field"><label>Date on the Examination Report</label><input type="date" name="exam_report_receipt_date" value="{{ old('exam_report_receipt_date') }}" required><small>Enter the date shown on the UKIPO report. Always check the report for the exact response deadline.</small></div>
                    <div class="err-field err-full"><label>Upload Examination Report PDF</label><input type="file" name="exam_report" accept=".pdf,.jpg,.jpeg,.png" required><small>Please upload the Examination Report. Without it, legal analysis cannot be completed.</small></div>
                    <div class="err-field"><label>Upload TM-A Acknowledgment</label><input type="file" name="tma_acknowledgment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"></div>
                    <div class="err-field"><label>Upload Logo / Device Mark</label><input type="file" name="logo_device_mark" accept=".pdf,.jpg,.jpeg,.png"></div>
                </div>
                <div class="err-warning">Failure to file a reply within the prescribed period may result in abandonment of the application.</div>
                <button class="err-btn" type="submit"><i class="bi bi-send"></i> Submit Objection Reply Request</button>
                <a class="err-back" href="{{ route('examination-reply.landing') }}">Back</a>
            </div>
        </form>
    </div>
@endsection
