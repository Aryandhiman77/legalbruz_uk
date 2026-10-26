<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentVerificationException;
use App\Services\PaymentInvoiceEmailService;
use App\Services\RevolutMerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevolutWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        RevolutMerchantService $revolut,
        PaymentInvoiceEmailService $invoiceEmails,
    ): JsonResponse {
        $rawPayload = $request->getContent();
        if (! $revolut->verifyWebhook(
            $rawPayload,
            $request->header('Revolut-Request-Timestamp'),
            $request->header('Revolut-Signature'),
        )) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $orderId = (string) ($request->input('order_id') ?? '');
        if ($orderId !== '') {
            try {
                $transaction = $revolut->syncWebhookOrder($orderId);
                if ($transaction?->status === 'completed') {
                    $invoiceEmails->sendToClientAndAdmins($transaction);
                }
            } catch (PaymentVerificationException $exception) {
                return response()->json(['message' => $exception->getMessage()], $exception->httpStatus());
            }
        }

        return response()->json(['received' => true]);
    }
}
