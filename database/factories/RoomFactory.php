<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'name' => 'Ruang '.$this->faker->unique()->word(),
            'location' => 'Gedung A Lantai '.$this->faker->numberBetween(1, 5),
            'capacity' => $this->faker->numberBetween(10, 60),
            'is_active' => true,
        ];
    }
}
