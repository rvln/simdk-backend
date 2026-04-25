<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitDistributionRequest;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;

class DistributionController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function submitDistribution(SubmitDistributionRequest $request)
    {
        $distribution = $this->inventoryService->recordDistribution([
            'inventory_id' => $request->inventory_id,
            'user_id' => Auth::id(),
            'qty' => $request->qty,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $distribution
        ], 201);
    }
}
