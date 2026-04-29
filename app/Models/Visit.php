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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function capacity()
    {
        return $this->belongsTo(Capacity::class);
    }
}
