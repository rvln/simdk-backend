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

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/resend-verification', [AuthController::class, 'resendVerification']);

Route::post('/donations', [DonationController::class, 'initiateDonation']);
Route::post('/donations/items', [DonationController::class, 'submitItemDonation']);
Route::post('/webhooks/midtrans', [WebhookController::class, 'handleMidtransWebhook']);

Route::get('/tracking/{tracking_code}', [TrackingController::class, 'trackDonation']);
Route::get('/inventories', [InventoryController::class, 'index']);

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

    // Visits (Pengunjung & Pengurus)
    Route::post('/visits', [VisitController::class, 'submitRequest']);
    Route::put('/admin/visits/{visit}/approve', [VisitController::class, 'approveRequest']);

    // Inventory & Distribution (Pengurus)
    Route::put('/admin/donations/items/{donation}/check-in', [ItemDonationController::class, 'processCheckIn']);
    Route::post('/admin/distributions', [DistributionController::class, 'submitDistribution']);

    // Reporting (Kepala Panti)
    Route::get('/reports', [ReportController::class, 'requestReport']);
});
