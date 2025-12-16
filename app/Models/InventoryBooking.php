<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\InventoryLogType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class InventoryBooking extends Model
{
    protected $fillable = [
        'booking_code',
        'requester_name',
        'requester_email',
        'requester_google_id',
        'unit',
        'purpose',
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
        'checked_out_at',
        'checked_out_by',
        'checkout_note',
        'returned_at',
        'returned_by',
        'return_note',
        'cancelled_at',
    ];

    protected $casts = [
        'status' => BookingStatus::class,
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'staff_approved_at' => 'datetime',
        'head_approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'returned_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Relationships
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(InventoryItem::class, 'inventory_booking_items')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(InventoryLog::class)->orderBy('created_at', 'desc');
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

    public function checkedOutByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function returnedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    // Generate unique booking code
    public static function generateBookingCode(): string
    {
        $year = now()->year;

        return DB::transaction(function () use ($year) {
            $sequence = DB::table('booking_sequences')
                ->where('type', 'INV')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($sequence) {
                $nextNumber = $sequence->last_number + 1;
                DB::table('booking_sequences')
                    ->where('type', 'INV')
                    ->where('year', $year)
                    ->update(['last_number' => $nextNumber, 'updated_at' => now()]);
            } else {
                $nextNumber = 1;
                DB::table('booking_sequences')->insert([
                    'type' => 'INV',
                    'year' => $year,
                    'last_number' => $nextNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return sprintf('INV-%d-%04d', $year, $nextNumber);
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

    public function canCheckOut(): bool
    {
        return $this->status === BookingStatus::APPROVED_HEAD;
    }

    public function canReturn(): bool
    {
        return $this->status === BookingStatus::CHECKED_OUT;
    }

    public function canCancel(): bool
    {
        return $this->status === BookingStatus::PENDING;
    }

    // Create log entry
    public function createLog(InventoryLogType $type, ?string $note = null): void
    {
        $this->logs()->create([
            'type' => $type,
            'note' => $note,
            'created_by' => auth()->id(),
        ]);
    }

    // Validation: Operating hours (08:00 - 16:00)
    public static function validateOperatingHours(\DateTime $startAt, \DateTime $endAt): array
    {
        $errors = [];
        $openTime = '08:00';
        $closeTime = '16:00';

        $startTime = $startAt->format('H:i');
        $endTime = $endAt->format('H:i');

        if ($startTime < $openTime) {
            $errors[] = "Jam mulai tidak boleh sebelum {$openTime}";
        }

        if ($endTime > $closeTime) {
            $errors[] = "Jam selesai tidak boleh setelah {$closeTime}";
        }

        if ($startAt >= $endAt) {
            $errors[] = "Jam selesai harus setelah jam mulai";
        }

        return $errors;
    }

    // Check for overlapping bookings for given items
    public static function checkItemOverlaps(array $itemIds, \DateTime $startAt, \DateTime $endAt, ?int $excludeBookingId = null): array
    {
        $conflicts = [];

        foreach ($itemIds as $itemId) {
            $item = InventoryItem::find($itemId);
            if ($item && $item->hasOverlappingBookings($startAt, $endAt, $excludeBookingId)) {
                $conflicts[] = $item->display_name;
            }
        }

        return $conflicts;
    }
}
