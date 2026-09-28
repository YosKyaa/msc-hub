<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'code' => 'INV-'.Str::upper(Str::random(6)),
            'name' => $this->faker->randomElement(['Kamera Mirrorless', 'Tripod', 'Lampu Studio', 'Clip-on Mic']),
            'category' => 'camera',
            'condition_status' => 'good',
            'is_active' => true,
        ];
    }
}
