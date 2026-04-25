<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\InventoryEnum;
use App\Models\ItemDonation;
use App\Models\Distribution;

class Inventory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'itemName',
        'category',
        'stock',
        'target_qty',
        'unit',
        'description',
    ];

    protected $casts = [
        'category' => InventoryEnum::class,
    ];

    public function itemDonations()
    {
        return $this->hasMany(ItemDonation::class);
    }

    public function distributions()
    {
        return $this->hasMany(Distribution::class);
    }
}
