<?php

namespace App\Services;

use App\Models\Visit;
use App\Models\Capacity;
use App\Enums\VisitStatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitService
{
    /**
     * Fetch visits eager loaded with user and capacity, with dynamic filters.
     * Filters: status (string), date (string YYYY-MM-DD), search (string).
     */
    public function getVisits(array $filters = [])
    {
        $query = Visit::with(['user', 'capacity']);

        if (!empty($filters['status']) && $filters['status'] !== 'ALL') {
            $query->where('status', $filters['status']);
        } elseif (empty($filters['status']))

        if (!empty($filters['date'])) {
            $query->whereHas('capacity', function ($q) use ($filters) {
                $q->where('date', $filters['date']);
            });
        }

        if (!empty($filters['search'])) {
    $query->whereHas('user', function ($q) use ($filters) {
        $q->where('name', 'LIKE', '%' . $filters['search'] . '%');
    });
}

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Approve a visit ensuring max 1 visit per session block.
     *
     * @param string $id
     * @param string|null $confirmedTime
     * @return Visit
     * @throws ValidationException
     */
    public function approveVisit(string $id, ?string $confirmedTime)
    {
        return DB::transaction(function () use ($id, $confirmedTime) {
            $visit = Visit::findOrFail($id);

            if ($visit->status !== VisitStatusEnum::PENDING) {
                throw ValidationException::withMessages(['status' => 'Kunjungan ini sudah diproses.']);
            }

            // Lock the capacity to prevent race conditions
            $capacity = Capacity::where('id', $visit->capacity_id)->lockForUpdate()->firstOrFail();

            // Mutex Guard: Enforce maximum 1 visit per session (or according to quota if quota is 1, but prompt says strictly 1-visit-per-session)
            if ($capacity->booked >= 1) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi ini sudah diisi oleh kunjungan lain. Maksimal 1 kunjungan per sesi.'
                ]);
            }

            $capacity->booked += 1;
            $capacity->save();

            $visit->status = VisitStatusEnum::APPROVED;
            if ($confirmedTime) {
                $visit->confirmed_time = $confirmedTime;
            }
            $visit->save();

            return $visit;
        });
    }

    /**
     * Reject a visit.
     *
     * @param string $id
     * @param string $reason
     * @return Visit
     */
    public function rejectVisit(string $id, string $reason)
    {
        return DB::transaction(function () use ($id, $reason) {
            $visit = Visit::findOrFail($id);

            if ($visit->status !== VisitStatusEnum::PENDING) {
                throw ValidationException::withMessages(['status' => 'Kunjungan ini sudah diproses.']);
            }

            $visit->status = VisitStatusEnum::REJECTED;
            $visit->rejection_reason = $reason;
            $visit->save();

            return $visit;
        });
    }

    /**
     * Mark a visit as needing reschedule and store the admin's recommendation.
     * Reuses the `rejection_reason` column to store recommendation notes —
     * avoids creating a new migration for a transient advisory field.
     *
     * No capacity mutation — this is purely a status transition + advisory note.
     *
     * @param string $id
     * @param string $recommendationNotes
     * @return Visit
     */
    public function requestReschedule(string $id, string $recommendationNotes)
    {
        return DB::transaction(function () use ($id, $recommendationNotes) {
            $visit = Visit::findOrFail($id);

            if ($visit->status !== VisitStatusEnum::PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya kunjungan berstatus PENDING yang dapat diminta jadwal ulang.',
                ]);
            }

            $visit->status = VisitStatusEnum::NEEDS_RESCHEDULE;
            $visit->rejection_reason = $recommendationNotes;
            $visit->save();

            return $visit;
        });
    }
}
