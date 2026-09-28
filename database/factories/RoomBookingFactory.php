<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Room;
use App\Models\RoomBooking;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<RoomBooking>
 */
class RoomBookingFactory extends Factory
{
    protected $model = RoomBooking::class;

    public function definition(): array
    {
        // Selalu pada jam kerja hari kerja: aturan validasi menolak
        // peminjaman di luar itu, dan catatan uji tidak boleh ikut tertolak.
        $mulai = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0);

        return [
            'booking_code' => 'RB-'.Str::upper(Str::random(8)),
            'room_id' => Room::factory(),
            'requester_name' => $this->faker->name(),
            'requester_email' => $this->faker->unique()->safeEmail(),
            'requester_phone' => '08123456789',
            'unit' => 'HIMATIF',
            'purpose' => $this->faker->sentence(6),
            'attendees' => 10,
            'start_at' => $mulai,
            'end_at' => $mulai->copy()->addHours(2),
            'status' => BookingStatus::PENDING,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::APPROVED_HEAD,
            'staff_approved_at' => now(),
            'head_approved_at' => now(),
        ]);
    }
}
