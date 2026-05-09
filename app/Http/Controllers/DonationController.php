<?php

namespace App\Http\Controllers;

use App\Http\Requests\InitiateDonationRequest;
use App\Http\Requests\SubmitItemDonationRequest;
use App\Services\PaymentService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Models\Donation;
use App\Enums\DonationStatusEnum;
use Illuminate\Support\Facades\DB;

class DonationController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private InventoryService $inventoryService
    ) {}

    /**
     * POST /api/donations
     * Initiates a financial (DANA) donation. Delegates entirely to PaymentService.
     */
    public function initiateDonation(InitiateDonationRequest $request)
    {
        try {
            $paymentChannel = $request->input('payment_channel', 'MIDTRANS');
            $paymentProof = $request->file('payment_proof');

            $result = $this->paymentService->initiateDonation(
                Auth::id(),
                [
                    'donorName'  => $request->donorName,
                    'donorEmail' => $request->donorEmail,
                    'donorPhone' => $request->donorPhone,
                ],
                (float) $request->amount,
                $paymentChannel,
                $paymentProof
            );

            return response()->json([
                'status' => 'success',
                'data'   => $result,
            ], 201);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * POST /api/donations/items
     * Submits an item (BARANG) donation via Smart Cart. Delegates to InventoryService.
     */
    public function submitItemDonation(SubmitItemDonationRequest $request)
    {
        try {
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
                'data'   => [
                    'tracking_code' => $donation->tracking_code,
                ]
            ], 201);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * PATCH /api/admin/donations/{id}/approve
     */
    public function approveManualDonation($id)
    {
        return DB::transaction(function () use ($id) {
            $donation = Donation::findOrFail($id);

            if ($donation->status->value !== DonationStatusEnum::PENDING->value) {
                return response()->json(['message' => 'Hanya donasi PENDING yang dapat disetujui.'], 422);
            }

            if ($donation->payment_channel !== 'MANUAL') {
                return response()->json(['message' => 'Hanya donasi MANUAL yang dapat disetujui melalui endpoint ini.'], 403);
            }

            $donation->update(['status' => DonationStatusEnum::SUCCESS->value]);

            return response()->json([
                'status' => 'success',
                'message' => 'Donasi manual berhasil disetujui.',
                'data' => $donation
            ]);
        });
    }

    /**
     * PATCH /api/admin/donations/{id}/reject
     */
    public function rejectManualDonation($id)
    {
        return DB::transaction(function () use ($id) {
            $donation = Donation::findOrFail($id);

            if ($donation->status->value !== DonationStatusEnum::PENDING->value) {
                return response()->json(['message' => 'Hanya donasi PENDING yang dapat ditolak.'], 422);
            }

            if ($donation->payment_channel !== 'MANUAL') {
                return response()->json(['message' => 'Hanya donasi MANUAL yang dapat ditolak melalui endpoint ini.'], 403);
            }

            $donation->update(['status' => DonationStatusEnum::REJECTED->value]);

            return response()->json([
                'status' => 'success',
                'message' => 'Donasi manual berhasil ditolak.',
                'data' => $donation
            ]);
        });
    }
}
