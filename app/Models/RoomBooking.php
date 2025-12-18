<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class RoomBooking extends Model
{
    protected $fillable = [
        'booking_code',
        'room_id',
        'requester_name',
        'requester_email',
        'requester_google_id',
        'unit',
        'purpose',
        'attendees',
        'start_at',
        'end_at',
        'status',
        'staff_approved_at',
        'staff_approved_by',
        'head_approved_at',
        'head_approved_by',
        'rejected_at',
        'rejected_by',
        'reject_reason',
        'cancelled_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => BookingStatus::class,
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'staff_approved_at' => 'datetime',
        'head_approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function staffApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_approved_by');
    }

    public function headApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_approved_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function inventoryItems(): BelongsToMany
    {
        return $this->belongsToMany(InventoryItem::class, 'room_booking_items')
            ->withPivot(['quantity', 'notes'])
            ->withTimestamps();
    }

    // Generate unique booking code
    public static function generateBookingCode(): string
    {
        $year = now()->year;

        return DB::transaction(function () use ($year) {
            $sequence = DB::table('booking_sequences')
                ->where('type', 'ROOM')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($sequence) {
                $nextNumber = $sequence->last_number + 1;
                DB::table('booking_sequences')
                    ->where('type', 'ROOM')
                    ->where('year', $year)
                    ->update(['last_number' => $nextNumber, 'updated_at' => now()]);
            } else {
                $nextNumber = 1;
                DB::table('booking_sequences')->insert([
                    'type' => 'ROOM',
                    'year' => $year,
                    'last_number' => $nextNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return sprintf('ROOM-%d-%04d', $year, $nextNumber);
        });
    }

    // Business Logic Helpers
    public function canStaffApprove(): bool
    {
        return $this->status === BookingStatus::PENDING;
    }

    public function canHeadApprove(): bool
    {
        return $this->status === BookingStatus::APPROVED_STAFF && $this->staff_approved_at !== null;
    }

    public function canReject(): bool
    {
        return in_array($this->status, [
            BookingStatus::PENDING,
            BookingStatus::APPROVED_STAFF,
        ]);
    }

    public function canCancel(): bool
    {
        return $this->status === BookingStatus::PENDING;
    }

    public function canComplete(): bool
    {
        return $this->status === BookingStatus::APPROVED_HEAD && $this->end_at <= now();
    }

    // Validation: Operating hours
    public function validateOperatingHours(): array
    {
        return $this->room->validateOperatingHours($this->start_at, $this->end_at);
    }

    // Check for overlapping bookings
    public function hasOverlap(): bool
    {
        return $this->room->hasOverlappingBookings($this->start_at, $this->end_at, $this->id);
    }

    // Get duration in hours
    public function getDurationHoursAttribute(): float
    {
        return $this->start_at->diffInMinutes($this->end_at) / 60;
    }
}
