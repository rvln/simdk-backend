<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DonationValidationService;
use App\Enums\RoleEnum;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DonationValidationController extends Controller
{
    public function __construct(private DonationValidationService $service) {}

    /**
     * Enforce that only PENGURUS_PANTI or KEPALA_PANTI can access these routes.
     */
    private function authorizeStaffRole(Request $request): void
    {
        $role = $request->user()?->role;

        // role may be a RoleEnum instance (cast) or a plain string
        $roleValue = $role instanceof \BackedEnum ? $role->value : (string) $role;

        if (!in_array($roleValue, [RoleEnum::PENGURUS_PANTI->value, RoleEnum::KEPALA_PANTI->value], true)) {
            abort(403, 'Akses ditolak. Hanya PENGURUS atau KEPALA PANTI yang dapat memvalidasi donasi.');
        }
    }

    /**
     * GET /api/validasi-donasi
     * Returns all PENDING_DELIVERY donations with eager-loaded item and inventory data.
     */
    public function index(Request $request)
    {
        $this->authorizeStaffRole($request);

        try {
            $donations = $this->service->getPendingDonations();

            return response()->json([
                'status' => 'success',
                'data'   => $donations,
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * POST /api/validasi-donasi/{id}/approve
     * Approve a PENDING_DELIVERY donation — increments inventory stock atomically.
     */
    public function approve(Request $request, string $id)
    {
        $this->authorizeStaffRole($request);

        try {
            $result = $this->service->approveDonation($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'Donasi berhasil divalidasi dan stok inventaris diperbarui.',
                'data'    => $result,
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * POST /api/validasi-donasi/{id}/reject
     * Reject a PENDING_DELIVERY donation — creates a RejectedLog audit entry.
     */
    public function reject(Request $request, string $id)
    {
        $this->authorizeStaffRole($request);

        $request->validate([
            'reason' => ['required', 'string', 'min:5'],
        ]);

        try {
            $result = $this->service->rejectDonation(
                donationId: $id,
                loggedBy:   $request->user()->id,
                reason:     $request->input('reason'),
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Donasi ditolak dan dicatat dalam log audit.',
                'data'    => $result,
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }
}
