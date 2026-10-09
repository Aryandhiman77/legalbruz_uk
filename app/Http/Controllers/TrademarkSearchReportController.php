<?php

namespace App\Http\Controllers;

use App\Models\TrademarkPricing;
use App\Models\TrademarkSearchReportRequest;
use App\Models\TrademarkSearchReportDocument;
use App\Exceptions\PaymentVerificationException;
use App\Services\RazorpayPaymentVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

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

    public function createOrder(TrademarkSearchReportRequest $reportRequest): JsonResponse
    {
        if ($reportRequest->payment_status === 'paid') {
            return response()->json(['status' => 'error', 'message' => 'This report request has already been paid.'], 422);
        }

        $keyId = (string) config('razorpay.key_id');
        $secret = (string) config('razorpay.key_secret');

        if ($keyId === '' || $secret === '') {
            return response()->json(['status' => 'error', 'message' => 'Online payment is temporarily unavailable. Please contact us for assistance.'], 503);
        }

        try {
            $response = Http::withBasicAuth($keyId, $secret)
                ->acceptJson()
                ->timeout((int) config('razorpay.timeout', 30))
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => (int) round((float) $reportRequest->amount * 100),
                    'currency' => $reportRequest->currency,
                    'receipt' => 'search_'.$reportRequest->id.'_'.now()->timestamp,
                    'notes' => [
                        'report_request_id' => $reportRequest->public_id,
                        'service' => 'Trademark Search Report',
                    ],
                ]);
        } catch (Throwable) {
            return response()->json(['status' => 'error', 'message' => 'The payment service is temporarily unavailable. Please try again.'], 502);
        }

        if (! $response->successful() || blank($response->json('id'))) {
            return response()->json(['status' => 'error', 'message' => 'The payment order could not be created. Please try again.'], 502);
        }

        $reportRequest->update(['razorpay_order_id' => $response->json('id')]);

        return response()->json([
            'status' => 'success',
            'order_id' => $response->json('id'),
            'amount' => (int) round((float) $reportRequest->amount * 100),
            'currency' => $reportRequest->currency,
            'key' => $keyId,
            'description' => 'Legal Bruz trademark search report',
            'name' => $reportRequest->name,
            'email' => $reportRequest->email,
            'phone' => $reportRequest->phone,
        ]);
    }

    public function verify(Request $request, TrademarkSearchReportRequest $reportRequest, RazorpayPaymentVerifier $verifier): JsonResponse
    {
        $validated = $request->validate([
            'razorpay_payment_id' => ['required', 'string', 'max:255'],
            'razorpay_order_id' => ['required', 'string', 'max:255'],
            'razorpay_signature' => ['required', 'string', 'max:255'],
        ]);

        if ($reportRequest->payment_status === 'paid') {
            if (! hash_equals((string) $reportRequest->razorpay_order_id, $validated['razorpay_order_id'])
                || ! hash_equals((string) $reportRequest->transaction_id, $validated['razorpay_payment_id'])) {
                return response()->json(['status' => 'error', 'message' => 'This report request is already linked to a different payment.'], 422);
            }

            return response()->json([
                'status' => 'success',
                'redirect_url' => route('trademark-search-report.success', $reportRequest),
            ]);
        }

        try {
            $verifier->verifyCaptured(
                $validated['razorpay_payment_id'],
                $validated['razorpay_order_id'],
                $validated['razorpay_signature'],
                (string) $reportRequest->razorpay_order_id,
                (int) round((float) $reportRequest->amount * 100),
                $reportRequest->currency,
            );
        } catch (PaymentVerificationException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], $exception->httpStatus());
        }

        $reportRequest->update([
            'payment_status' => 'paid',
            'report_status' => TrademarkSearchReportRequest::PAYMENT_RECEIVED,
            'transaction_id' => $validated['razorpay_payment_id'],
            'paid_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'redirect_url' => route('trademark-search-report.success', $reportRequest),
        ]);
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
