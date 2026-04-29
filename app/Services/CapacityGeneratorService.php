<?php

namespace App\Services;

use App\Models\Capacity;
use App\Enums\TimeSlotEnum;
use Carbon\Carbon;

class CapacityGeneratorService
{
    /**
     * Generate visitor capacities using a Just-In-Time Rolling Window approach.
     * Ensures exactly $daysAhead of capacities exist without destroying manual overrides.
     */
    public function generateForWindow(int $daysAhead = 30): void
    {
        $today = Carbon::today();
        
        for ($i = 0; $i <= $daysAhead; $i++) {
            $date = $today->copy()->addDays($i);
            $isWeekend = $date->isWeekend();

            // Determine active slots rule
            // Mon-Fri: AFTERNOON, EVENING active. MORNING, NIGHT inactive.
            // Sat-Sun: MORNING, AFTERNOON, EVENING active. NIGHT inactive.
            $slotRules = [
                TimeSlotEnum::MORNING->value => $isWeekend ? true : false,
                TimeSlotEnum::AFTERNOON->value => true,
                TimeSlotEnum::EVENING->value => true,
                TimeSlotEnum::NIGHT->value => false,
            ];

            foreach ($slotRules as $slotValue => $isActive) {
                // firstOrCreate protects manual admin overrides from being touched
                Capacity::firstOrCreate(
                    [
                        'date' => $date->toDateString(),
                        'slot' => $slotValue,
                    ],
                    [
                        'quota' => 1,
                        'booked' => 0,
                        'is_active' => $isActive,
                    ]
                );
            }
        }
    }
}
