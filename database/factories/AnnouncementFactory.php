<?php

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        $judul = $this->faker->sentence(4);

        return [
            'title' => $judul,
            'slug' => Str::slug($judul).'-'.Str::random(6),
            'summary' => $this->faker->sentence(12),
            'content' => '<p>'.$this->faker->paragraph().'</p>',
            'category' => 'announcement',
            'published_at' => now(),
            'is_pinned' => false,
            'is_active' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['published_at' => null, 'is_active' => false]);
    }
}
