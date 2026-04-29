<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\TimeSlotEnum;

class Capacity extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'date',
        'slot',
        'quota',
        'booked',
        'is_active',
    ];

    protected $casts = [
        'date' => 'date',
        'slot' => TimeSlotEnum::class,
        'is_active' => 'boolean',
    ];

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }
}
