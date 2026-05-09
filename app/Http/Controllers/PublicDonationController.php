<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Donation;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use App\Enums\DonationStatusEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Enums\DonationTypeEnum;

class PublicDonationController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    /**
     * Store a newly created item donation from the public form.
     *
     * AGENTS.md §2 Compliance: Controller handles ONLY input validation
     * and service delegation. All business logic (locking, TTL, capacity checks)
     * lives in InventoryService::submitPublicDonation().
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'donorName'      => 'required|string|max:255',
            'donorPhone'     => 'required|string|max:255',
            'items'          => 'required|array|min:1',
            'items.*.id'     => 'required|string',
            'items.*.name'   => 'required_if:items.*.id,MANUAL|string|max:255',
            'items.*.qty'    => 'required|integer|min:1',
        ]);

        try {
            $donation = $this->inventoryService->submitPublicDonation(
                [
                    'donorName'  => $validated['donorName'],
                    'donorPhone' => $validated['donorPhone'],
                    'donorEmail' => $request->input('donorEmail', null),
                ],
                $validated['items']
            );

            return response()->json([
                'status'        => 'success',
                'tracking_code' => $donation->tracking_code,
            ], 201);
        } catch (ValidationException $e) {
            // Clean 422 JSON for frontend interception
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    /**
     * Retrieve a donation by its tracking code for public hydration.
     */
    public function show($tracking_code)
    {
        $donation = Donation::with('itemDonations')
            ->where('tracking_code', $tracking_code)
            ->first();

        if (!$donation) {
            return response()->json(['message' => 'Resi tidak ditemukan.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $donation
        ]);
    }

    /**
     * Cancel a pending public financial donation.
     */
    public function cancel(Request $request, $id)
    {
        return DB::transaction(function () use ($id) {
            $donation = Donation::findOrFail($id);

            if ($donation->status->value !== DonationStatusEnum::PENDING->value) {
                return response()->json(['message' => 'Hanya donasi dengan status PENDING yang dapat dibatalkan.'], 422);
            }

            $donation->update(['status' => DonationStatusEnum::EXPIRED->value]);

            return response()->json(['status' => 'success', 'message' => 'Donasi berhasil dibatalkan.']);
        });
    }

    /**
     * Show invoice data for a specific financial donation.
     * Incorporates polling mechanism to prevent Midtrans webhook race condition.
     */
    public function showInvoice($id)
    {
        $donation = Donation::find($id);

        if (!$donation) {
            return response()->json(['message' => 'Faktur tidak ditemukan.'], 404);
        }

        if ($donation->type->value !== DonationTypeEnum::DANA->value) {
            return response()->json(['message' => 'Faktur ini bukan untuk donasi finansial.'], 403);
        }

        // Webhook Race Condition Guard
        if ($donation->status->value === DonationStatusEnum::PENDING->value) {
            return response()->json([
                'status' => 'PROCESSING',
                'message' => 'Waiting for payment gateway confirmation...'
            ], 202);
        }

        if ($donation->status->value !== DonationStatusEnum::SUCCESS->value) {
            return response()->json(['message' => 'Faktur tidak valid (Kedaluwarsa/Batal).'], 403);
        }

        // PII Masking
        $donorEmail = $donation->donorEmail ? $this->maskEmail($donation->donorEmail) : null;
        $donorPhone = $donation->donorPhone ? $this->maskPhone($donation->donorPhone) : null;

        return response()->json([
            'status' => 'SUCCESS',
            'data' => [
                'id' => $donation->id,
                'tracking_code' => $donation->tracking_code ?? $donation->order_id,
                'amount' => $donation->amount,
                'payment_type' => $donation->payment_type,
                'created_at' => $donation->created_at,
                'donorName' => $donation->donorName,
                'donorEmail' => $donorEmail,
                'donorPhone' => $donorPhone,
            ]
        ]);
    }

    /**
     * Generate and download PDF for a successful donation.
     */
    public function downloadPdf($id)
    {
        $donation = Donation::find($id);

        if (!$donation || $donation->status->value !== DonationStatusEnum::SUCCESS->value) {
            abort(404, 'Faktur tidak ditemukan atau belum lunas.');
        }

        $pdf = Pdf::loadView('pdf.donation-invoice', compact('donation'));
        return $pdf->download('invoice-' . ($donation->tracking_code ?? $donation->order_id ?? $donation->id) . '.pdf');
    }

    private function maskEmail($email) {
        $parts = explode('@', $email);
        if (count($parts) != 2) return $email;
        $name = $parts[0];
        $maskedName = strlen($name) > 2 ? substr($name, 0, 1) . str_repeat('*', strlen($name) - 2) . substr($name, -1) : $name;
        return $maskedName . '@' . $parts[1];
    }

    private function maskPhone($phone) {
        if (strlen($phone) < 6) return $phone;
        return substr($phone, 0, 4) . str_repeat('*', strlen($phone) - 6) . substr($phone, -2);
    }
}
