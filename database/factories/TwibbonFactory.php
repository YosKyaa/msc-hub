<?php

namespace Database\Factories;

use App\Models\Twibbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Twibbon>
 */
class TwibbonFactory extends Factory
{
    protected $model = Twibbon::class;

    public function definition(): array
    {
        $judul = 'Twibbon '.$this->faker->unique()->words(3, true);

        return [
            'title' => $judul,
            'slug' => Str::slug($judul),
            'description' => $this->faker->sentence(),
            'caption' => 'Saya siap mengikuti kegiatan ini! #JGU #MSC',
            'frame_path' => 'twibbons/'.Str::random(10).'.png',
            'is_published' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['is_published' => false]);
    }

    public function scheduled(): static
    {
        return $this->state(['starts_at' => now()->addDay()]);
    }

    public function ended(): static
    {
        return $this->state(['starts_at' => now()->subWeek(), 'ends_at' => now()->subDay()]);
    }
}
