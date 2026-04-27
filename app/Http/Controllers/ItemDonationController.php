<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcessCheckInRequest;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ItemDonationController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    /**
     * PUT /api/admin/donations/items/{donation}/check-in
     * Delegates Phase 2 check-in/rejection entirely to InventoryService.
     * Controller passes only primitive identifiers — zero Eloquent here.
     */
    public function processCheckIn(ProcessCheckInRequest $request, string $donation)
    {
        try {
            if ($request->action === 'accept') {
                $this->inventoryService->checkInItem($donation);

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Item successfully checked in and inventory adjusted.'
                ]);
            }

            // Rejection path — delegate to Service with donation_id FK + reason
            $this->inventoryService->rejectItem(
                $donation,
                Auth::id(),
                $request->reason,
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Item officially rejected.'
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }
}
