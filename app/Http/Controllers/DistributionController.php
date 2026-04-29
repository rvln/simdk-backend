<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\SubmitDistributionRequest;
use App\Services\InventoryService;
use App\Enums\RoleEnum;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DistributionController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    /**
     * Enforce that only PENGURUS_PANTI or KEPALA_PANTI can access distribution endpoints.
     * Handles both cast RoleEnum instances and raw string values.
     */
    private function authorizeStaffRole(Request $request): void
    {
        $role = $request->user()?->role;
        $roleValue = $role instanceof \BackedEnum ? $role->value : (string) $role;

        if (!in_array($roleValue, [RoleEnum::PENGURUS_PANTI->value, RoleEnum::KEPALA_PANTI->value], true)) {
            abort(403, 'Akses ditolak. Hanya PENGURUS atau KEPALA PANTI yang dapat mengelola distribusi.');
        }
    }

    /**
     * GET /api/distribusi
     * Returns full distribution history with inventory and user relations.
     * Protected: staff-only endpoint.
     */
    public function index(Request $request)
    {
        $this->authorizeStaffRole($request);

        try {
            $distributions = $this->inventoryService->getAllDistributions();

            return response()->json([
                'status' => 'success',
                'data'   => $distributions,
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * POST /api/distribusi
     * Records a single-item distribution with atomic stock deduction.
     *
     * AGENTS.md §3 — Logistics Distribution Auditability:
     * `target_recipient` and `notes` are MANDATORY — validated by SubmitDistributionRequest.
     *
     * Delegates to InventoryService::recordDistribution() which wraps everything in
     * DB::transaction() with lockForUpdate() on the Inventory row.
     */
    public function store(SubmitDistributionRequest $request)
    {
        $this->authorizeStaffRole($request);

        try {
            $distribution = $this->inventoryService->recordDistribution([
                'inventory_id'     => $request->inventory_id,
                'user_id'          => $request->user()->id,
                'qty'              => $request->qty,
                'target_recipient' => $request->target_recipient,
                'notes'            => $request->notes,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Distribusi berhasil dicatat dan stok diperbarui.',
                'data'    => $distribution,
            ], 201);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * POST /api/admin/distributions  (Legacy — preserved for backward compatibility)
     * Older endpoint used by the existing SubmitDistribution flow.
     * Delegates to the same service method as store().
     */
    public function submitDistribution(SubmitDistributionRequest $request)
    {
        try {
            $distribution = $this->inventoryService->recordDistribution([
                'inventory_id'     => $request->inventory_id,
                'user_id'          => $request->user()->id,
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
