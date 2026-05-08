<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use App\Models\Donation;
use App\Models\Visit;
use App\Models\Capacity;
use App\Enums\DonationStatusEnum;
use App\Enums\VisitStatusEnum;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Automated Expiration Handling via Task Scheduler
|--------------------------------------------------------------------------
*/

// 1. Donation Expiration Logic
Schedule::call(function () {
    DB::transaction(function () {
        Donation::where('status', DonationStatusEnum::PENDING->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->update(['status' => DonationStatusEnum::EXPIRED->value]);
    });
})->everyMinute()->name('sweep_expired_donations')->withoutOverlapping();

// 2. Visit No-Show Logic
Schedule::call(function () {
    // Enforce timezone defined in AGENTS.md
    $today = Carbon::today('Asia/Makassar')->format('Y-m-d');

    // Target: Visits where status is APPROVED AND visit_date (< today())
    $ghostVisits = Visit::where('status', VisitStatusEnum::APPROVED->value)
        ->whereHas('capacity', function ($query) use ($today) {
            $query->where('date', '<', $today);
        })->get();

    foreach ($ghostVisits as $visit) {
        DB::transaction(function () use ($visit) {
            // Update visit status
            $visit->update(['status' => VisitStatusEnum::NO_SHOW->value]);

            // Safely decrement the capacity booked count using pessimistic locking
            if ($visit->capacity_id) {
                $capacity = Capacity::where('id', $visit->capacity_id)->lockForUpdate()->first();
                if ($capacity && $capacity->booked > 0) {
                    $capacity->decrement('booked');
                }
            }
        });
    }
})->everyMinute()->name('sweep_no_show_visits')->withoutOverlapping();
