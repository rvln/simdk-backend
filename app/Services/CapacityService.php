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
     * Creates a new visit request after validating initial capacity availability.
     * Does NOT reserve a slot — reservation only happens upon approval via approveVisit().
     *
     * @param string $userId      Authenticated user UUID.
     * @param string $capacityId  Target capacity slot UUID.
     * @return Visit
     * @throws Exception
     */
    public function createVisitRequest(string $userId, string $capacityId): Visit
    {
        $capacity = Capacity::find($capacityId);

        if (!$capacity) {
            throw new Exception("Capacity slot not found.");
        }

        if ($capacity->quota <= $capacity->booked) {
            throw new Exception("Selected slot is fully booked.");
        }

        return Visit::create([
            'user_id'     => $userId,
            'capacity_id' => $capacityId,
            'status'      => VisitStatusEnum::PENDING->value,
        ]);
    }

    /**
     * Approves a visit while enforcing pessimistic locking to prevent race conditions.
     * Uses capacity_id FK for direct lookup instead of date+slot composite query.
     *
     * @param Visit $visit
     * @return Visit
     * @throws Exception
     */
    public function approveVisit(Visit $visit): Visit
    {
        return DB::transaction(function () use ($visit) {
            // Pessimistic Locking via capacity_id FK (direct lookup)
            $capacity = Capacity::where('id', $visit->capacity_id)
                ->lockForUpdate()
                ->first();

            if (!$capacity) {
                throw new Exception("Capacity not configured for this visit.");
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
