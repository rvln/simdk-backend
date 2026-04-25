<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitVisitRequest;
use App\Http\Requests\ApproveVisitRequest;
use App\Models\Visit;
use App\Services\CapacityService;
use Illuminate\Support\Facades\Auth;

class VisitController extends Controller
{
    public function __construct(private CapacityService $capacityService) {}

    public function submitRequest(SubmitVisitRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $visit = $user->visits()->create([
            'date' => $request->date,
            'slot' => $request->slot,
            'status' => \App\Enums\VisitStatusEnum::PENDING->value,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $visit
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
