<?php

namespace App\Services;

use App\Models\Visit;
use App\Models\Capacity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Enums\VisitStatusEnum;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CapacityService
{
    /**
     * Creates a new visit request after enforcing:
     *   1. Authentication State Constraint (email_verified_at != NULL)
     *   2. Initial capacity availability check
     *
     * Does NOT reserve a slot — reservation only happens upon approval via approveVisit().
     *
     * UML Ref: Activity Diagram §6 — [Constraint Check] email_verified_at != NULL
     * UML Ref: Sequence Diagram §SD-6 — [Initial Validation] checkAvailability
     *
     * @param string $userId      Authenticated user UUID.
     * @param string $capacityId  Target capacity slot UUID.
     * @return Visit
     */
    public function createVisitRequest(string $userId, string $capacityId): Visit
    {
        // Authentication State Constraint (AGENTS.md §3)
        $user = User::find($userId);

        if (!$user) {
            throw new HttpException(404, 'User not found.');
        }

        if (!$user->email_verified_at) {
            throw new HttpException(403, 'Email belum diverifikasi. Silakan verifikasi email Anda terlebih dahulu.');
        }

        // Initial capacity availability check
        $capacity = Capacity::find($capacityId);

        if (!$capacity) {
            throw new HttpException(404, 'Capacity slot not found.');
        }

        if ($capacity->quota <= $capacity->booked) {
            throw new HttpException(422, 'Selected slot is fully booked.');
        }

        return Visit::create([
            'user_id'     => $userId,
            'capacity_id' => $capacityId,
            'status'      => VisitStatusEnum::PENDING->value,
        ]);
    }

    /**
     * Approves a visit while enforcing Pessimistic Locking to prevent Race Conditions.
     *
     * UML Ref: Sequence Diagram §SD-6 — [Re-Validation — CRITICAL SECTION]
     *   1. BEGIN TRANSACTION
     *   2. lockCapacityForUpdate(capacity_id) — SELECT FOR UPDATE
     *   3. Evaluate (quota - booked) > 0
     *   4. If true: increment booked, update status to APPROVED, COMMIT
     *   5. If false: ROLLBACK, throw CapacityFull exception
     *
     * @param string $visitId  The UUID of the visit to approve.
     * @return array           Serializable visit data.
     */
    public function approveVisit(string $visitId): array
    {
        return DB::transaction(function () use ($visitId) {
            $visit = Visit::find($visitId);

            if (!$visit) {
                throw new HttpException(404, 'Visit not found.');
            }

            if ($visit->status !== VisitStatusEnum::PENDING->value) {
                throw new HttpException(422, 'Only pending visits can be approved.');
            }

            // Pessimistic Locking via capacity_id FK (direct lookup with SELECT FOR UPDATE)
            $capacity = Capacity::where('id', $visit->capacity_id)
                ->lockForUpdate()
                ->first();

            if (!$capacity) {
                throw new HttpException(404, 'Capacity not configured for this visit.');
            }

            if ($capacity->quota <= $capacity->booked) {
                // Race condition: capacity became full between submission and approval
                $visit->update(['status' => VisitStatusEnum::REJECTED->value]);
                throw new HttpException(409, 'Capacity is full. Visit rejected due to race condition.');
            }

            // Safely increment booked quota
            $capacity->increment('booked');

            // Update visit status to approved
            $visit->update(['status' => VisitStatusEnum::APPROVED->value]);

            return $visit->load('capacity')->toArray();
        });
    }

    /**
     * Rejects a visit request by setting its status to REJECTED.
     *
     * @param string $visitId  The UUID of the visit to reject.
     * @return array           Serializable visit data.
     */
    public function rejectVisit(string $visitId): array
    {
        $visit = Visit::find($visitId);

        if (!$visit) {
            throw new HttpException(404, 'Visit not found.');
        }

        if ($visit->status !== VisitStatusEnum::PENDING->value) {
            throw new HttpException(422, 'Only pending visits can be rejected.');
        }

        $visit->update(['status' => VisitStatusEnum::REJECTED->value]);

        return $visit->toArray();
    }

    /**
     * Retrieves a visit record with its capacity relationship as a serializable array.
     * Used by the Controller to attach data to JSON responses without touching Eloquent.
     *
     * @param string $visitId
     * @return array
     */
    public function getVisitWithCapacity(string $visitId): array
    {
        $visit = Visit::with('capacity')->find($visitId);

        if (!$visit) {
            throw new HttpException(404, 'Visit not found.');
        }

        return $visit->toArray();
    }
}
