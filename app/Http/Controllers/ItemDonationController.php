<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcessCheckInRequest;
use App\Models\Donation;
use App\Services\InventoryService;

class ItemDonationController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function processCheckIn(ProcessCheckInRequest $request, Donation $donation)
    {
        if ($request->action === 'accept') {
            $this->inventoryService->checkInItem($donation);

            return response()->json([
                'status' => 'success',
                'message' => 'Item successfully checked in and inventory adjusted.'
            ]);
        }

        // If 'reject'
        $donation->update(['status' => \App\Enums\DonationStatusEnum::REJECTED->value]);
        
        $request->user()->rejectedLogs()->create([
            'itemName' => $donation->itemDonations->first()?->itemName_snapshot ?? 'Unknown',
            'reason' => $request->reason,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Item officially rejected.'
        ]);
    }
}
