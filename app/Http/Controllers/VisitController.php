<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitVisitRequest;
use App\Http\Requests\ApproveVisitRequest;
use App\Models\Visit;
use App\Services\CapacityService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;

class VisitController extends Controller
{
    public function __construct(
        private CapacityService $capacityService,
        private InventoryService $inventoryService
    ) {}

    public function submitRequest(SubmitVisitRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Delegate visit creation to CapacityService (validates slot availability)
        $visit = $this->capacityService->createVisitRequest($user->id, $request->capacity_id);

        // Unified endpoint: if visitor brings donation items, process Smart Cart
        $donation = null;
        if ($request->boolean('bringsDonation') && $request->has('items')) {
            $donation = $this->inventoryService->submitPreSubmission(
                $user->id,
                [
                    'donorName'  => $user->name,
                    'donorEmail' => $user->email,
                    'donorPhone' => $request->input('donorPhone', ''),
                ],
                $request->items
            );
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'visit'    => $visit->load('capacity'),
                'donation' => $donation,
            ]
        ], 201);
    }

    public function approveRequest(ApproveVisitRequest $request, Visit $visit)
    {
        if ($request->action === 'approve') {
            $this->capacityService->approveVisit($visit);
        } else {
            $visit->update(['status' => \App\Enums\VisitStatusEnum::REJECTED->value]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Visit request processed.'
        ]);
    }
}
