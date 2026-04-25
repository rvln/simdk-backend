<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Capacity;
use App\Enums\TimeSlotEnum;
use Carbon\Carbon;

class CapacitySeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $date = Carbon::now()->addDays($i)->format('Y-m-d');
            
            Capacity::create([
                'date' => $date,
                'slot' => TimeSlotEnum::MORNING->value,
                'quota' => 5,
                'booked' => 0,
            ]);

            Capacity::create([
                'date' => $date,
                'slot' => TimeSlotEnum::AFTERNOON->value,
                'quota' => 5,
                'booked' => 0,
            ]);
        }
    }
}
