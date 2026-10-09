<?php

namespace App\Http\Controllers;

use App\Models\TrademarkSearchReportRequest;
use App\Models\TrademarkSearchReportDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminTrademarkSearchReportController extends Controller
{
    public function index(Request $request): View
    {
        $reportRequests = TrademarkSearchReportRequest::query()
            ->withCount('documents')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.trim((string) $request->string('search')).'%';
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('brand_name', 'like', $search)
                        ->orWhere('transaction_id', 'like', $search);
                });
            })
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->string('payment_status')))
            ->when($request->filled('report_status'), fn ($query) => $query->where('report_status', $request->string('report_status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.trademark-search-reports.index', [
            'reportRequests' => $reportRequests,
            'reportStatuses' => TrademarkSearchReportRequest::statusOptions(),
        ]);
    }

    public function show(TrademarkSearchReportRequest $reportRequest): View
    {
        $reportRequest->load('documents');

        return view('admin.trademark-search-reports.show', [
            'reportRequest' => $reportRequest,
            'reportStatuses' => TrademarkSearchReportRequest::statusOptions(),
        ]);
    }

    public function update(Request $request, TrademarkSearchReportRequest $reportRequest): RedirectResponse
    {
        $validated = $request->validate([
            'report_status' => ['required', Rule::in(array_keys(TrademarkSearchReportRequest::statusOptions()))],
            'admin_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $updates = [
            'report_status' => $validated['report_status'],
            'admin_notes' => $validated['admin_notes'] ?? null,
        ];

        $reportRequest->update($updates);

        return redirect()
            ->route('admin.trademark-search-reports.show', $reportRequest)
            ->with('success', 'Search report status and notes updated successfully.');
    }

    public function storeDocument(Request $request, TrademarkSearchReportRequest $reportRequest): RedirectResponse
    {
        $validated = $request->validate([
            'document_name' => ['required', 'string', 'max:150'],
            'document_file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $file = $request->file('document_file');

        $reportRequest->documents()->create([
            'document_name' => trim($validated['document_name']),
            'file_path' => $file->store('trademark-search-reports/'.$reportRequest->id, 'local'),
            'original_name' => $file->getClientOriginalName(),
            'uploaded_at' => now(),
        ]);

        if (! in_array($reportRequest->report_status, [TrademarkSearchReportRequest::REPORT_READY, TrademarkSearchReportRequest::COMPLETED], true)) {
            $reportRequest->update(['report_status' => TrademarkSearchReportRequest::REPORT_READY]);
        }

        return redirect()
            ->route('admin.trademark-search-reports.show', $reportRequest)
            ->with('success', 'Document added successfully.');
    }

    public function download(TrademarkSearchReportRequest $reportRequest, TrademarkSearchReportDocument $document)
    {
        abort_unless($document->trademark_search_report_request_id === $reportRequest->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download(
            $document->file_path,
            $document->original_name,
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function destroyDocument(TrademarkSearchReportRequest $reportRequest, TrademarkSearchReportDocument $document): RedirectResponse
    {
        abort_unless($document->trademark_search_report_request_id === $reportRequest->id, 404);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        if (! $reportRequest->documents()->exists()
            && in_array($reportRequest->report_status, [TrademarkSearchReportRequest::REPORT_READY, TrademarkSearchReportRequest::COMPLETED], true)) {
            $reportRequest->update([
                'report_status' => $reportRequest->payment_status === 'paid'
                    ? TrademarkSearchReportRequest::IN_REVIEW
                    : TrademarkSearchReportRequest::AWAITING_PAYMENT,
            ]);
        }

        return redirect()
            ->route('admin.trademark-search-reports.show', $reportRequest)
            ->with('success', 'PDF report removed successfully.');
    }
}
