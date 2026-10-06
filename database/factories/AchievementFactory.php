<?php

namespace Database\Factories;

use App\Enums\AchievementCategory;
use App\Models\Achievement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Achievement>
 */
class AchievementFactory extends Factory
{
    protected $model = Achievement::class;

    public function definition(): array
    {
        return [
            'title' => 'Juara '.$this->faker->numberBetween(1, 3).' '.$this->faker->words(3, true),
            'awarded_by' => $this->faker->randomElement([
                'Kementerian Pendidikan Tinggi',
                'LLDIKTI Wilayah III',
                'Asosiasi Humas Perguruan Tinggi',
                'Jakarta Global University',
            ]),
            'category' => $this->faker->randomElement(AchievementCategory::cases())->value,
            'level' => $this->faker->randomElement(['Kampus', 'Regional', 'Nasional']),
            'description' => $this->faker->sentence(12),
            'achieved_at' => $this->faker->dateTimeBetween('-3 years', 'now'),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
