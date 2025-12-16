<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $units = [
            'Fakultas Teknik',
            'Fakultas Ekonomi',
            'Fakultas Hukum',
            'Fakultas MIPA',
            'Fakultas Kedokteran',
            'Rektorat',
            'BEM',
            'UKM',
            null,
        ];

        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'unit' => fake()->randomElement($units),
            'event_date' => fake()->dateTimeBetween('-2 years', '+6 months'),
            'location' => fake()->optional()->city(),
            'status' => fake()->randomElement(ProjectStatus::cases()),
            'created_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Active,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Archived,
        ]);
    }
}
