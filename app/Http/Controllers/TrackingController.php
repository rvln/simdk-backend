<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TrackingController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    /**
     * GET /api/tracking/{tracking_code}
     * Public endpoint for tracking donation status.
     * Delegates to PaymentService which returns a limited DTO per SRS NFR-04.
     */
    public function trackDonation(string $tracking_code)
    {
        try {
            $trackingData = $this->paymentService->getPublicTrackingData($tracking_code);

            return response()->json([
                'status' => 'success',
                'data'   => $trackingData,
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }
}
