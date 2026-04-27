<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PaymentService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WebhookController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    /**
     * POST /api/webhooks/midtrans
     * Receives Midtrans webhook callback. Delegates all processing to PaymentService.
     * Always returns HTTP 200 on successful processing (including idempotent hits)
     * to prevent Midtrans from retrying.
     */
    public function handleMidtransWebhook(Request $request)
    {
        try {
            $this->paymentService->processWebhook($request->all());

            return response()->json(['status' => 'success'], 200);
        } catch (HttpException $e) {
            // Even on known errors, return 200 to Midtrans to prevent retry storms.
            // Log the error for internal investigation.
            \Illuminate\Support\Facades\Log::error(
                "Webhook processing error: {$e->getMessage()}",
                ['payload' => $request->all()]
            );

            return response()->json(['status' => 'success'], 200);
        }
    }
}
