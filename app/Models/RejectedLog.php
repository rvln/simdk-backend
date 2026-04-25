<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RejectedLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'donation_id',
        'itemName',
        'reason',
        'logged_by',
    ];

    public function donation()
    {
        return $this->belongsTo(Donation::class);
    }

    public function logger()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
