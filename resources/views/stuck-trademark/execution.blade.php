@extends('layouts.app')

@section('content')
    @php
        $displayTimezone = config('app.timezone', 'Europe/London');
        $visibleFinalReports = $case->executionDocuments
            ->where('document_type', 'Final report')
            ->values();
    @endphp

    <style>
        .client-execution-page {
            color: #10233f;
        }

        .client-execution-card {
            overflow: hidden;
            border: 0;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
        }

        .client-execution-card + .client-execution-card {
            margin-top: 18px;
        }

        .client-execution-header {
            padding: 18px 22px;
            background: #27466d;
            color: #ffffff;
        }

        .client-execution-header h3 {
            margin: 0;
            color: #ffffff;
            font-size: 1.1rem;
            font-weight: 900;
        }

        .client-execution-body {
            padding: 22px;
        }

        .client-execution-row {
            padding: 14px 0;
            border-bottom: 1px solid #edf2f7;
        }

        .client-execution-row:last-child {
            border-bottom: 0;
        }
    </style>

    <div class="container py-4 client-execution-page">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">Execution Progress</h1>
                <p class="text-muted mb-0">{{ $case->case_number }} | {{ $case->trademark_name }}</p>
            </div>
            <a href="{{ route('stuck-trademark.show', $case) }}" class="btn btn-outline-secondary">Back to Case</a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <section class="client-execution-card">
            <div class="client-execution-header"><h3>Current Case Status</h3></div>
            <div class="client-execution-body">
                <p class="mb-1"><strong>Status:</strong> {{ $case->execution_status ?: $case->status_label }}</p>
                <p class="mb-0"><strong>Current stage:</strong> {{ $case->current_stage ?: 'Execution In Progress' }}</p>
            </div>
        </section>

        <section class="client-execution-card">
            <div class="client-execution-header"><h3>Progress Updates</h3></div>
            <div class="client-execution-body">
                @forelse ($case->executionUpdates as $update)
                    <div class="client-execution-row">
                        <strong>{{ $update->title }}</strong>
                        <div class="text-muted small">{{ $update->stage }} | {{ $update->created_at->timezone($displayTimezone)->format('d M Y, h:i A') }}</div>
                        @if ($update->note)
                            <p class="mb-1 mt-2">{{ $update->note }}</p>
                        @endif
                        @if ($update->file_path)
                            <a href="{{ route('storage.public.view', $update->file_path) }}" target="_blank">View attached file</a>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0">No execution updates shared yet.</p>
                @endforelse
            </div>
        </section>

        <section class="client-execution-card">
            <div class="client-execution-header"><h3>Shared Documents</h3></div>
            <div class="client-execution-body">
                @forelse ($case->executionDocuments as $document)
                    <div class="client-execution-row">
                        <strong>{{ $document->document_title }}</strong>
                        <div class="text-muted small">{{ $document->document_type ?: 'Execution document' }}</div>
                        <a href="{{ route('storage.public.view', $document->file_path) }}" target="_blank">View document</a>
                    </div>
                @empty
                    <p class="text-muted mb-0">No execution documents shared yet.</p>
                @endforelse
            </div>
        </section>

        <section class="client-execution-card">
            <div class="client-execution-header"><h3>Missing Document Requests</h3></div>
            <div class="client-execution-body">
                @forelse ($case->documentRequests as $requestItem)
                    <div class="client-execution-row">
                        <strong>{{ $requestItem->document_name }}</strong>
                        <div class="text-muted small">Status: {{ $requestItem->status }}</div>
                        @if ($requestItem->message)
                            <p class="mb-2 mt-2">{{ $requestItem->message }}</p>
                        @endif
                        @if ($requestItem->uploaded_file)
                            <a href="{{ route('storage.public.view', $requestItem->uploaded_file) }}" target="_blank">View uploaded file</a>
                        @else
                            <form action="{{ route('client.trademark-execution.document-request.upload', [$case, $requestItem]) }}" method="POST" enctype="multipart/form-data" class="mt-2">
                                @csrf
                                <div class="d-flex gap-2 flex-wrap">
                                    <input type="file" name="file" class="form-control" required>
                                    <button type="submit" class="btn btn-primary">Upload</button>
                                </div>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0">No missing documents requested right now.</p>
                @endforelse
            </div>
        </section>

        @if ($visibleFinalReports->isNotEmpty())
            <section class="client-execution-card">
                <div class="client-execution-header"><h3>Final Report</h3></div>
                <div class="client-execution-body">
                    @foreach ($visibleFinalReports as $report)
                        <div class="client-execution-row">
                            <strong>{{ $report->document_title }}</strong>
                            <a href="{{ route('storage.public.view', $report->file_path) }}" target="_blank" class="d-block mt-1">View final report</a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
