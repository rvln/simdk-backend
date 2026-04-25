<?php

namespace App\Services;

use App\Models\Visit;
use App\Models\Capacity;
use Illuminate\Support\Facades\DB;
use App\Enums\VisitStatusEnum;
use Exception;

class CapacityService
{
    /**
     * Approves a visit while enforcing pessimistic locking to prevent race conditions.
     *
     * @param Visit $visit
     * @return Visit
     * @throws Exception
     */
    public function approveVisit(Visit $visit): Visit
    {
        return DB::transaction(function () use ($visit) {
            // Pessimistic Locking on the exact date and slot
            $capacity = Capacity::where('date', $visit->date)
                ->where('slot', $visit->slot)
                ->lockForUpdate()
                ->first();

            if (!$capacity) {
                throw new Exception("Capacity not configured for this date and slot.");
            }

            if ($capacity->quota <= $capacity->booked) {
                // If race condition triggered and capacity is full
                $visit->update(['status' => VisitStatusEnum::REJECTED->value]);
                throw new Exception("Capacity is full. Visit rejected due to race condition.");
            }

            // Safely increment booked quota
            $capacity->increment('booked');

            // Update visit status
            $visit->update(['status' => VisitStatusEnum::APPROVED->value]);

            return $visit;
        });
    }
}
