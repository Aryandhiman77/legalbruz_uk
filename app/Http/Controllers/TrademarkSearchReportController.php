<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentVerificationException;
use App\Models\PaymentTransaction;
use App\Models\TrademarkPricing;
use App\Models\TrademarkSearchReportDocument;
use App\Models\TrademarkSearchReportRequest;
use App\Services\RevolutMerchantService;
use App\Services\PaymentInvoiceEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TrademarkSearchReportController extends Controller
{
    public function create(): View
    {
        return view('pages.trademark-search-report', [
            'reportFee' => TrademarkPricing::amountFor(TrademarkPricing::SEARCH),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'max:30'],
            'brand_name' => ['required', 'string', 'max:180'],
            'business_activity' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['nullable', 'max:0'],
        ]);

        $amount = TrademarkPricing::amountFor(TrademarkPricing::SEARCH);
        $reportRequest = TrademarkSearchReportRequest::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => Auth::guard('web')->id(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'brand_name' => $validated['brand_name'],
            'business_activity' => $validated['business_activity'],
            'amount' => $amount,
            'currency' => 'GBP',
            'payment_status' => 'pending',
            'report_status' => TrademarkSearchReportRequest::AWAITING_PAYMENT,
        ]);

        return redirect()->route('trademark-search-report.payment', $reportRequest);
    }

    public function payment(TrademarkSearchReportRequest $reportRequest): View
    {
        return view('pages.trademark-search-report-payment', compact('reportRequest'));
    }

    public function createOrder(TrademarkSearchReportRequest $reportRequest, RevolutMerchantService $revolut): JsonResponse
    {
        if ($reportRequest->payment_status === 'paid') {
            return response()->json(['status' => 'error', 'message' => 'This report request has already been paid.'], 422);
        }

        try {
            $transaction = $revolut->createHostedOrder(
                $reportRequest,
                'trademark_search_report',
                (float) $reportRequest->amount,
                $reportRequest->currency,
                'Legal Bruz trade mark search report',
                route('trademark-search-report.payment.confirm', $reportRequest),
                $reportRequest->user_id,
                $reportRequest->email,
                ['report_request_id' => $reportRequest->public_id],
            );
        } catch (PaymentVerificationException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], $exception->httpStatus());
        }

        $reportRequest->update(['payment_reference' => $transaction->provider_order_id]);

        return response()->json([
            'status' => 'success',
            'order_id' => $transaction->provider_order_id,
            'checkout_url' => $transaction->checkout_url,
            'amount' => (int) round((float) $reportRequest->amount * 100),
            'currency' => $reportRequest->currency,
        ]);
    }

    public function confirm(
        TrademarkSearchReportRequest $reportRequest,
        RevolutMerchantService $revolut,
        PaymentInvoiceEmailService $invoiceEmails,
    ): RedirectResponse
    {
        if ($reportRequest->payment_status === 'paid') {
            return redirect()->route('trademark-search-report.success', $reportRequest);
        }

        $transaction = PaymentTransaction::query()->whereMorphedTo('payable', $reportRequest)->latest('id')->first();
        if (! $transaction) {
            return redirect()->route('trademark-search-report.payment', $reportRequest)->with('error', 'No payment order was found.');
        }
        try {
            $transaction = $revolut->verifyCompleted($reportRequest, $transaction->provider_order_id);
        } catch (PaymentVerificationException $exception) {
            return redirect()->route('trademark-search-report.payment', $reportRequest)->with('error', $exception->getMessage());
        }

        $reportRequest->update([
            'payment_status' => 'paid',
            'report_status' => TrademarkSearchReportRequest::PAYMENT_RECEIVED,
            'payment_reference' => $transaction->provider_order_id,
            'transaction_id' => $transaction->provider_payment_id,
            'paid_at' => $transaction->paid_at ?? now(),
        ]);

        $invoiceEmails->sendToClientAndAdmins($transaction);

        return redirect()->route('trademark-search-report.success', $reportRequest)->with('success', 'Payment confirmed successfully.');
    }

    public function success(TrademarkSearchReportRequest $reportRequest): View|RedirectResponse
    {
        if ($reportRequest->payment_status !== 'paid') {
            return redirect()->route('trademark-search-report.payment', $reportRequest);
        }

        $reportRequest->load('documents');

        return view('pages.trademark-search-report-success', compact('reportRequest'));
    }

    public function download(TrademarkSearchReportRequest $reportRequest, TrademarkSearchReportDocument $document)
    {
        $user = Auth::guard('web')->user();
        abort_unless($user && TrademarkSearchReportRequest::query()->visibleTo($user)->whereKey($reportRequest->getKey())->exists(), 403);
        abort_unless($reportRequest->payment_status === 'paid', 403);
        abort_unless($document->trademark_search_report_request_id === $reportRequest->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download(
            $document->file_path,
            $document->original_name,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
