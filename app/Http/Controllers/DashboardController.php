<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use App\Models\Donation;
use App\Models\Distribution;
use App\Models\Capacity;
use App\Enums\VisitStatusEnum;
use App\Enums\DonationStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class DashboardController extends Controller
{
    private function authorizeStaffRole(Request $request): void
    {
        $userRole = $request->user()?->role;
        $roleValue = $userRole instanceof \App\Enums\RoleEnum ? $userRole->value : $userRole;

        if (!in_array($roleValue, ['PENGURUS_PANTI', 'KEPALA_PANTI'], true)) {
            abort(403, 'Akses ditolak. Fitur ini hanya untuk pengurus operasional dan pimpinan.');
        }
    }

    public function getOverview(Request $request): JsonResponse
    {
        $this->authorizeStaffRole($request);

        // 1. Metrics
        $pendingVisits = Visit::where('status', VisitStatusEnum::PENDING)
            ->get()
            ->filter(fn ($visit) => !$visit->is_expired)
            ->count();
        $pendingDonations = Donation::whereIn('status', [
            DonationStatusEnum::PENDING_DELIVERY, 
            DonationStatusEnum::PENDING
        ])->count();
        
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        
        $weeklyCapacities = Capacity::whereBetween('date', [$startOfWeek, $endOfWeek])->get();
        $totalQuota = $weeklyCapacities->sum('quota');
        $totalBooked = $weeklyCapacities->sum('booked');
        $capacityRemaining = max(0, $totalQuota - $totalBooked);

        // 2. Today's Agenda
        $today = Carbon::today()->toDateString();
        $todaysVisits = Visit::with(['user', 'capacity'])
            ->where('status', VisitStatusEnum::APPROVED)
            ->whereHas('capacity', function ($q) use ($today) {
                $q->where('date', $today);
            })
            ->get()
            ->map(function ($visit) {
                return [
                    'id' => $visit->id,
                    'applicant' => $visit->user->name ?? 'Anonim',
                    'session' => $visit->capacity->slot->value ?? $visit->capacity->slot,
                    'time' => $visit->confirmed_time ?? 'TBA',
                ];
            });

        // 3. Activity Logs (Virtual)
        $latestDonations = Donation::latest('updated_at')->take(3)->get()->map(function ($d) {
            return [
                'id' => $d->id,
                'title' => 'Donasi Baru: ' . ($d->donorName ?? 'Hamba Allah'),
                'subtitle' => 'Status: ' . ($d->status->value ?? $d->status),
                'time_diff' => $d->updated_at->diffForHumans(),
                'domain' => 'donasi',
                'updated_at' => $d->updated_at,
            ];
        });

        $latestVisits = Visit::with('user')->latest('updated_at')->take(3)->get()->map(function ($v) {
            return [
                'id' => $v->id,
                'title' => 'Kunjungan: ' . ($v->user->name ?? 'Pengunjung'),
                'subtitle' => 'Status: ' . ($v->status->value ?? $v->status),
                'time_diff' => $v->updated_at->diffForHumans(),
                'domain' => 'kunjungan',
                'updated_at' => $v->updated_at,
            ];
        });

        $activityLogs = collect($latestDonations)
            ->merge($latestVisits)
            ->sortByDesc('updated_at')
            ->take(5)
            ->values();

        // 4. Logistics Audit
        $logisticsAudit = Distribution::with(['user', 'inventory'])->latest('distributed_at')->take(5)->get()->map(function ($dist) {
            return [
                'id' => $dist->id,
                'title' => $dist->qty . ' ' . ($dist->inventory->unit ?? 'pcs') . ' ' . ($dist->inventory->itemName ?? 'Barang'),
                'actor' => $dist->user->name ?? 'Admin',
                'time_formatted' => $dist->distributed_at->toIso8601String(),
                'status_badge' => 'TERDISTRIBUSI',
                'target_recipient' => $dist->target_recipient,
            ];
        });

        return response()->json([
            'metrics' => [
                'pending_visits' => $pendingVisits,
                'pending_donations' => $pendingDonations,
                'weekly_capacity_remaining' => $capacityRemaining,
                'weekly_total_capacity' => $totalQuota,
            ],
            'todays_agenda' => $todaysVisits,
            'activity_logs' => $activityLogs,
            'logistics_audit' => $logisticsAudit,
        ], 200);
    }
}
