<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\InventoryBooking;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryBooking>
 */
class InventoryBookingFactory extends Factory
{
    protected $model = InventoryBooking::class;

    public function definition(): array
    {
        $mulai = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0);

        return [
            'booking_code' => 'IB-'.Str::upper(Str::random(8)),
            'requester_name' => $this->faker->name(),
            'requester_email' => $this->faker->unique()->safeEmail(),
            'requester_phone' => '08123456789',
            'unit' => 'HIMATIF',
            'purpose' => $this->faker->sentence(6),
            'start_at' => $mulai,
            'end_at' => $mulai->copy()->addHours(4),
            'status' => BookingStatus::PENDING,
        ];
    }
}
