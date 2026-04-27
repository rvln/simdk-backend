<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitVisitRequest;
use App\Http\Requests\ApproveVisitRequest;
use App\Services\CapacityService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VisitController extends Controller
{
    public function __construct(
        private CapacityService $capacityService,
        private InventoryService $inventoryService
    ) {}

    /**
     * POST /api/visits
     * Delegates visit creation + optional Smart Cart donation to Service layer.
     * Controller passes only primitive identifiers — zero Eloquent here.
     */
    public function submitRequest(SubmitVisitRequest $request)
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $userId = $user->id;

            // Delegate visit creation to CapacityService
            // Service independently validates email_verified_at
            $visit = $this->capacityService->createVisitRequest(
                $userId,
                $request->capacity_id,
            );

            // Unified endpoint: if visitor brings donation items, process Smart Cart
            $donation = null;
            if ($request->boolean('bringsDonation') && $request->has('items')) {
                $donation = $this->inventoryService->submitPreSubmission(
                    $userId,
                    [
                        'donorName'  => $user->name,
                        'donorEmail' => $user->email,
                        'donorPhone' => $request->input('donorPhone', ''),
                    ],
                    $request->items
                );
            }

            // Retrieve serializable visit data via Service (no Eloquent in Controller)
            $visitData = $this->capacityService->getVisitWithCapacity($visit->id);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'visit'    => $visitData,
                    'donation' => $donation,
                ]
            ], 201);
        } catch (HttpException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * PUT /api/admin/visits/{visit}/approve
     * Delegates approval/rejection entirely to CapacityService.
     * Route Model Binding resolves the visit UUID — Controller passes only the ID string.
     */
    public function approveRequest(ApproveVisitRequest $request, string $visit)
    {
        try {
            if ($request->action === 'approve') {
                $this->capacityService->approveVisit($visit);
            } else {
                $this->capacityService->rejectVisit($visit);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Visit request processed.'
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }
}
