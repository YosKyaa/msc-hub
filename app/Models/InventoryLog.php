<?php

namespace App\Models;

use App\Enums\InventoryLogType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLog extends Model
{
    protected $fillable = [
        'inventory_booking_id',
        'type',
        'note',
        'created_by',
    ];

    protected $casts = [
        'type' => InventoryLogType::class,
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(InventoryBooking::class, 'inventory_booking_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
