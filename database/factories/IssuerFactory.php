<?php

namespace Database\Factories;

use App\Models\Issuer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class IssuerFactory extends Factory
{
    protected $model = Issuer::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'code' => Str::upper(Str::slug(Str::limit($name, 12, ''), '')).fake()->unique()->numberBetween(10, 999),
            'address_line' => fake()->address(),
            'is_house' => false,
            'is_active' => true,
        ];
    }

    public function house(): static
    {
        return $this->state([
            'name' => 'Media & Strategic Communications, Jakarta Global University',
            'code' => 'MSC-JGU',
            'is_house' => true,
        ]);
    }
}
