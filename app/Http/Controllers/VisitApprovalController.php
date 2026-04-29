<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\VisitService;
use Illuminate\Http\JsonResponse;

class VisitApprovalController extends Controller
{
    
    private function authorizeStaffRole(Request $request): void
{
    $userRole = $request->user()?->role;
    $roleValue = $userRole instanceof \App\Enums\RoleEnum ? $userRole->value : $userRole;

    if (!in_array($roleValue, ['PENGURUS_PANTI', 'KEPALA_PANTI'], true)) {
        abort(403, 'Akses ditolak. Fitur ini hanya untuk pengurus operasional dan pimpinan.');
    }
}
    protected VisitService $visitService;

    public function __construct(VisitService $visitService)
    {
        $this->visitService = $visitService;
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeStaffRole($request);
        
        $filters = $request->only(['search', 'date', 'status']);
        $visits = $this->visitService->getVisits($filters);
        
        return response()->json(['data' => $visits]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $this->authorizeStaffRole($request);
        $validated = $request->validate([
            'confirmed_time' => 'nullable|string'
        ]);

        $visit = $this->visitService->approveVisit($id, $validated['confirmed_time'] ?? null);

        return response()->json([
            'message' => 'Kunjungan berhasil disetujui',
            'data' => $visit
        ], 200);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $this->authorizeStaffRole($request);
        $validated = $request->validate([
            'reason' => 'required|string|min:3'
        ]);

        $visit = $this->visitService->rejectVisit($id, $validated['reason']);

        return response()->json([
            'message' => 'Kunjungan berhasil ditolak',
            'data' => $visit
        ], 200);
    }

    public function requestReschedule(Request $request, string $id): JsonResponse
    {
        $this->authorizeStaffRole($request);
        $validated = $request->validate([
            'recommendation_notes' => 'required|string|min:3'
        ]);

        $visit = $this->visitService->requestReschedule($id, $validated['recommendation_notes']);

        return response()->json([
            'message' => 'Permintaan reschedule berhasil dikirim',
            'data' => $visit
        ], 200);
    }
}
