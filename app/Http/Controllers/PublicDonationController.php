<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Donation;
use App\Services\InventoryService;

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
}
