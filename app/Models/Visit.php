<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\VisitStatusEnum;

class Visit extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'capacity_id',
        'status',
        'confirmed_time',
        'rejection_reason',
    ];

    protected $casts = [
        'status' => VisitStatusEnum::class,
    ];

    protected $appends = ['is_expired'];

    public function getIsExpiredAttribute()
    {
        if ($this->status->value !== VisitStatusEnum::PENDING->value) {
            return false;
        }

        if (!$this->relationLoaded('capacity')) {
            $this->load('capacity');
        }

        if (!$this->capacity) {
            return false;
        }

        $visitDate = $this->capacity->date->format('Y-m-d');
        
        $slotBoundaryMap = [
            'MORNING' => '10:00:00',
            'AFTERNOON' => '14:00:00',
            'EVENING' => '16:00:00',
            'NIGHT' => '20:00:00',
        ];

        $slotValue = $this->capacity->slot instanceof \BackedEnum ? $this->capacity->slot->value : $this->capacity->slot;
        $boundaryTime = $slotBoundaryMap[$slotValue] ?? '23:59:59';

        $boundaryDatetime = \Carbon\Carbon::parse("{$visitDate} {$boundaryTime}", 'Asia/Makassar');
        $now = \Carbon\Carbon::now('Asia/Makassar');

        return $now->greaterThan($boundaryDatetime);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function capacity()
    {
        return $this->belongsTo(Capacity::class);
    }
}
