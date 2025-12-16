<?php

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\Platform;
use App\Models\Asset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        $happenedAt = fake()->dateTimeBetween('-2 years', 'now');

        return [
            'project_id' => fake()->boolean(70) ? Project::factory() : null,
            'title' => fake()->sentence(4),
            'asset_type' => fake()->randomElement(AssetType::cases()),
            'platform' => fake()->randomElement(Platform::cases()),
            'source_link' => fake()->optional(0.8)->url(),
            'output_link' => fake()->optional(0.6)->url(),
            'happened_at' => $happenedAt,
            'year' => $happenedAt->format('Y'),
            'status' => fake()->randomElement(AssetStatus::cases()),
            'pic_user_id' => fake()->boolean(70) ? User::factory() : null,
            'created_by' => User::factory(),
            'notes' => fake()->optional()->paragraph(),
            'is_featured' => fake()->boolean(10),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssetStatus::Draft,
        ]);
    }

    public function final(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssetStatus::Final,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssetStatus::Published,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function photo(): static
    {
        return $this->state(fn (array $attributes) => [
            'asset_type' => AssetType::Photo,
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'asset_type' => AssetType::Video,
        ]);
    }

    public function design(): static
    {
        return $this->state(fn (array $attributes) => [
            'asset_type' => AssetType::Design,
        ]);
    }
}
