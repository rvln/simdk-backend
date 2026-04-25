<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemDonation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'donation_id',
        'inventory_id',
        'itemName_snapshot',
        'qty',
    ];

    public function donation()
    {
        return $this->belongsTo(Donation::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }
}
