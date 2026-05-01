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
        'priority',
        'stock',
        'target_qty',
        'unit',
        'description',
    ];

    protected $casts = [
        'category' => InventoryEnum::class,
        'priority' => \App\Enums\PriorityEnum::class,
    ];

    protected $appends = [
        'terkumpul_bulan_ini',
        'status_kebutuhan',
        'is_disabled',
        'next_available_date',
    ];

    public function getTerkumpulBulanIniAttribute()
    {
        return $this->itemDonations()
            ->whereHas('donation', function ($query) {
                $query->where('status', \App\Enums\DonationStatusEnum::SUCCESS->value);
            })
            ->whereMonth('created_at', \Carbon\Carbon::now('Asia/Makassar')->month)
            ->whereYear('created_at', \Carbon\Carbon::now('Asia/Makassar')->year)
            ->sum('qty');
    }

    public function getStatusKebutuhanAttribute()
    {
        return $this->terkumpul_bulan_ini >= $this->target_qty ? 'TERPENUHI' : 'SEDANG BERLANGSUNG';
    }

    public function getIsDisabledAttribute()
    {
        return $this->status_kebutuhan === 'TERPENUHI';
    }

    public function getNextAvailableDateAttribute()
    {
        \Carbon\Carbon::setLocale('id');
        return \Carbon\Carbon::now('Asia/Makassar')->addMonth()->startOfMonth()->translatedFormat('j F Y');
    }

    public function itemDonations()
    {
        return $this->hasMany(ItemDonation::class);
    }

    public function distributions()
    {
        return $this->hasMany(Distribution::class);
    }
}
