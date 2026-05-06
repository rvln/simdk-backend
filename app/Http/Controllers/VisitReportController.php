<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVisitReportRequest;
use App\Services\VisitReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * VisitReportController — Visitor Context
 *
 * Handles report submission by authenticated visitors.
 * Zero business logic here — delegates entirely to VisitReportService.
 */
class VisitReportController extends Controller
{
    public function __construct(private VisitReportService $visitReportService) {}

    /**
     * POST /api/visit-reports
     *
     * Submit a new visit report. Requires auth:sanctum.
     * The visitor must own the visit, and the visit must be COMPLETED.
     */
    public function store(StoreVisitReportRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $imageFiles = $request->hasFile('images') ? $request->file('images') : [];

            $report = $this->visitReportService->submitReport(
                $request->validated(),
                $imageFiles,
                $user
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Laporan kunjungan berhasil dikirim. Menunggu moderasi.',
                'data'    => $report,
            ], 201);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * GET /api/visit-reports/my
     *
     * List the authenticated visitor's own reports.
     */
    public function myReports(Request $request): JsonResponse
    {
        $reports = \App\Models\VisitReport::with(['visit.capacity'])
            ->where('user_id', $request->user()->id)
            ->latest('created_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $reports,
        ]);
    }
}
