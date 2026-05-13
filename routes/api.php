<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\ItemDonationController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\DistributionController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\DonationValidationController;
use App\Http\Controllers\PublicDonationController;
use App\Http\Controllers\PublicTransparencyController;
use App\Http\Controllers\VisitReportController;
use App\Http\Controllers\AdminReportModerationController;
use App\Http\Controllers\Auth\SocialiteController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/auth/google/redirect', [SocialiteController::class, 'redirectToGoogle'])->name('google.login');
Route::get('/auth/google/callback', [SocialiteController::class, 'handleGoogleCallback'])->name('google.callback');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/resend-verification', [AuthController::class, 'resendVerification']);

Route::post('/donasi/finansial', [DonationController::class, 'initiateDonation']);
Route::post('/donasi/barang', [DonationController::class, 'submitItemDonation']);
Route::post('/webhooks/midtrans', [WebhookController::class, 'handleMidtransWebhook']);

Route::get('/tracking/{tracking_code}', [TrackingController::class, 'trackDonation']);
Route::get('/inventories', [InventoryController::class, 'publicIndex']);
Route::get('/capacities', [\App\Http\Controllers\CapacityController::class, 'index']);
Route::get('/public/katalog-kebutuhan', [InventoryController::class, 'getPublicCatalog']);
Route::post('/public/donasi-barang', [PublicDonationController::class, 'store']);
Route::get('/public/donasi-barang/{tracking_code}', [PublicDonationController::class, 'show']);
Route::patch('/public/donations/{id}/cancel', [PublicDonationController::class, 'cancel']);
Route::get('/public/donations/{id}/invoice', [PublicDonationController::class, 'showInvoice']);
Route::get('/public/donations/{id}/invoice/download', [PublicDonationController::class, 'downloadPdf']);

// Public Transparency Page — PII-masked, paginated, read-only endpoints
Route::get('/public/transparansi/donasi', [PublicTransparencyController::class, 'donations']);
Route::get('/public/transparansi/distribusi', [PublicTransparencyController::class, 'distributions']);
Route::get('/public/transparansi/kebutuhan', [PublicTransparencyController::class, 'inventories']);
Route::get('/public/transparansi/kunjungan', [PublicTransparencyController::class, 'visits']);
Route::get('/public/transparansi/laporan', [PublicTransparencyController::class, 'visitReports']);
Route::get('/public/kunjungan/upcoming', [PublicTransparencyController::class, 'upcomingVisits']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Auth — Token Revocation (G-01 Security Hotfix)
    Route::post('/logout', [AuthController::class, 'logout']);

    // Auth — Fetch current user session (RBAC backbone)
    Route::get('/user', function (\Illuminate\Http\Request $request) {
        return response()->json(['data' => $request->user()]);
    });

    // Dashboard BFF
    Route::get('/dashboard/overview', [\App\Http\Controllers\DashboardController::class, 'getOverview']);

    // Visits (Pengunjung & Pengurus)
    Route::post('/visits', [VisitController::class, 'submitRequest']);
    Route::get('/user/visits', [VisitController::class, 'myVisits']);
    Route::get('/user/donations', [DonationController::class, 'myDonations']);
    Route::patch('/visits/{id}/resolve', [VisitController::class, 'resolve']);
    Route::put('/visits/{id}/reschedule', [VisitController::class, 'reschedule']);

    // Visit Approval Domain (Pengurus & Kepala Panti)
    Route::get('/kunjungan/manage', [\App\Http\Controllers\VisitApprovalController::class, 'index']);
    Route::post('/kunjungan/{id}/approve', [\App\Http\Controllers\VisitApprovalController::class, 'approve']);
    Route::post('/kunjungan/{id}/reject', [\App\Http\Controllers\VisitApprovalController::class, 'reject']);
    Route::post('/kunjungan/{id}/request-reschedule', [\App\Http\Controllers\VisitApprovalController::class, 'requestReschedule']);

    // Inventory & Distribution (Pengurus)
    Route::put('/admin/donations/items/{donation}/check-in', [ItemDonationController::class, 'processCheckIn']);
    Route::post('/admin/distributions', [DistributionController::class, 'submitDistribution']); // Legacy

    // Distribusi Dashboard (Pengurus & Kepala Panti only)
    // Role gate enforced inside DistributionController::authorizeStaffRole()
    Route::get('/distribusi',  [DistributionController::class, 'index']);
    Route::post('/distribusi', [DistributionController::class, 'store']);

    // Reporting (Kepala Panti)
    Route::get('/reports', [ReportController::class, 'requestReport']);

    // Kelola Kebutuhan — Inventory Catalog CRUD (Pengurus & Kepala Panti only)
    // Role gate is enforced inside InventoryController::authorizeStaffRole()
    Route::apiResource('kebutuhan', InventoryController::class);

    // Validasi Donasi — Check-in / Rejection (Pengurus & Kepala Panti only)
    Route::get('/validasi-donasi', [DonationValidationController::class, 'index']);
    Route::post('/validasi-donasi/{id}/approve', [DonationValidationController::class, 'approve']);
    Route::post('/validasi-donasi/{id}/reject',  [DonationValidationController::class, 'reject']);

    // Admin Manual Financial Donation Verification
    Route::patch('/admin/donations/{id}/approve', [DonationController::class, 'approveManualDonation']);
    Route::patch('/admin/donations/{id}/reject', [DonationController::class, 'rejectManualDonation']);

    // UGC Visit Reports — Visitor context (any authenticated user)
    Route::post('/visit-reports', [VisitReportController::class, 'store']);
    Route::get('/visit-reports/my', [VisitReportController::class, 'myReports']);

    // UGC Visit Reports — Admin Moderation (Pengurus & Kepala Panti only)
    // Role gate enforced inside AdminReportModerationController::authorizeStaffRole()
    Route::get('/admin/visit-reports', [AdminReportModerationController::class, 'index']);
    Route::patch('/admin/visit-reports/{id}/moderate', [AdminReportModerationController::class, 'moderate']);
});
