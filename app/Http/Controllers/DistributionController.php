<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitDistributionRequest;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DistributionController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    /**
     * POST /api/admin/distributions
     * Records a distribution with atomic stock deduction.
     * Passes target_recipient + notes for auditability compliance.
     * Delegates entirely to InventoryService — zero Eloquent here.
     */
    public function submitDistribution(SubmitDistributionRequest $request)
    {
        try {
            $distribution = $this->inventoryService->recordDistribution([
                'inventory_id'     => $request->inventory_id,
                'user_id'          => Auth::id(),
                'qty'              => $request->qty,
                'target_recipient' => $request->target_recipient,
                'notes'            => $request->notes,
            ]);

            return response()->json([
                'status' => 'success',
                'data'   => $distribution,
            ], 201);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }
}
