<?php

namespace App\Http\Controllers;

use App\Http\Requests\InitiateDonationRequest;
use App\Http\Requests\SubmitItemDonationRequest;
use App\Services\PaymentService;
use App\Services\InventoryService;
use App\Models\Donation;
use App\Enums\DonationTypeEnum;
use App\Enums\DonationStatusEnum;
use Illuminate\Support\Facades\Auth;

class DonationController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private InventoryService $inventoryService
    ) {}

    public function initiateDonation(InitiateDonationRequest $request)
    {
        // Logic delegating to service and generating Midtrans snapshot
        // BRS: Creates transaction as Pending, calls gateway, returns checkout URL.
        $donation = Donation::create([
            'user_id' => Auth::id(),
            'donorName' => $request->donorName,
            'donorEmail' => $request->donorEmail,
            'donorPhone' => $request->donorPhone,
            'type' => DonationTypeEnum::DANA->value,
            'amount' => $request->amount,
            'status' => DonationStatusEnum::PENDING->value,
            'tracking_code' => $this->paymentService->generateTrackingCode(),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'transaction_id' => $donation->id,
                'checkout_url' => 'https://app.sandbox.midtrans.com/snap/v3/redacted'
            ]
        ]);
    }

    public function submitItemDonation(SubmitItemDonationRequest $request)
    {
        $validated = $request->validated();

        $donation = $this->inventoryService->submitPreSubmission(
            Auth::id(),
            [
                'donorName'  => $validated['donorName'],
                'donorEmail' => $validated['donorEmail'],
                'donorPhone' => $validated['donorPhone'],
            ],
            $validated['items']
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'tracking_code' => $donation->tracking_code
            ]
        ], 201);
    }
}
