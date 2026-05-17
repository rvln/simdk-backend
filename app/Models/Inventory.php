<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\InventoryEnum;
use App\Enums\DonationStatusEnum;
use App\Models\ItemDonation;
use App\Models\Distribution;

class Inventory extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

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
        'virtual_stock',
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

    /**
     * Virtual Stock — sum of qty from item donations where the parent donation
     * is PENDING_DELIVERY and NOT yet expired (expires_at > now).
     * This represents "inbound" items that are soft-booked but not yet physically received.
     */
    public function getVirtualStockAttribute()
    {
        return (int) $this->itemDonations()
            ->whereHas('donation', function ($query) {
                $query->where('status', DonationStatusEnum::PENDING_DELIVERY->value)
                      ->where('expires_at', '>', now());
            })
            ->sum('qty');
    }

    public function getStatusKebutuhanAttribute()
    {
        return ($this->stock + $this->virtual_stock) >= $this->target_qty ? 'TERPENUHI' : 'SEDANG BERLANGSUNG';
    }

    public function getIsDisabledAttribute()
    {
        return ($this->stock + $this->virtual_stock) >= $this->target_qty;
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
