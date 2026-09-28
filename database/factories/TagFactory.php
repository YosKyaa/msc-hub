<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $nama = $this->faker->unique()->word();

        return [
            'name' => $nama,
            'slug' => Str::slug($nama).'-'.Str::random(4),
        ];
    }
}
