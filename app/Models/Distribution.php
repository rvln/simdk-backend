<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Distribution extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'inventory_id',
        'user_id',
        'qty',
        'target_recipient',
        'notes',
        'distributed_at',
    ];

    protected $casts = [
        'distributed_at' => 'datetime',
    ];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
