<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = [
        'name',
        'location',
        'open_time',
        'close_time',
        'capacity',
        'facilities',
        'is_active',
    ];

    protected $casts = [
        'open_time' => 'datetime:H:i',
        'close_time' => 'datetime:H:i',
        'is_active' => 'boolean',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class);
    }

    public function hasOverlappingBookings(\DateTime $startAt, \DateTime $endAt, ?int $excludeBookingId = null): bool
    {
        $query = $this->bookings()
            ->whereIn('status', array_map(fn ($s) => $s->value, BookingStatus::roomActiveStatuses()))
            ->where(function ($q) use ($startAt, $endAt) {
                $q->where(function ($q2) use ($startAt, $endAt) {
                    $q2->where('start_at', '<', $endAt)
                       ->where('end_at', '>', $startAt);
                });
            });

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query->exists();
    }

    public function validateOperatingHours(\DateTime $startAt, \DateTime $endAt): array
    {
        $errors = [];

        $openTime = $this->open_time ? $this->open_time->format('H:i') : '08:00';
        $closeTime = $this->close_time ? $this->close_time->format('H:i') : '16:00';

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
}
