<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\InventoryCategory;
use App\Enums\InventoryCondition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'category',
        'condition_status',
        'notes',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'category' => InventoryCategory::class,
        'condition_status' => InventoryCondition::class,
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(InventoryBooking::class, 'inventory_booking_items')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function isBookable(): bool
    {
        return $this->is_active && $this->condition_status->isBookable();
    }

    public function getDisplayNameAttribute(): string
    {
        return "[{$this->code}] {$this->name}";
    }

    public function hasOverlappingBookings(\DateTime $startAt, \DateTime $endAt, ?int $excludeBookingId = null): bool
    {
        $query = $this->bookings()
            ->whereIn('inventory_bookings.status', array_map(fn ($s) => $s->value, BookingStatus::activeStatuses()))
            ->where(function ($q) use ($startAt, $endAt) {
                $q->where(function ($q2) use ($startAt, $endAt) {
                    $q2->where('start_at', '<', $endAt)
                       ->where('end_at', '>', $startAt);
                });
            });

        if ($excludeBookingId) {
            $query->where('inventory_bookings.id', '!=', $excludeBookingId);
        }

        return $query->exists();
    }
}
