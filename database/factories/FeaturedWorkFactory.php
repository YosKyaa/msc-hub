<?php

namespace Database\Factories;

use App\Models\FeaturedWork;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FeaturedWork>
 */
class FeaturedWorkFactory extends Factory
{
    protected $model = FeaturedWork::class;

    public function definition(): array
    {
        $judul = $this->faker->sentence(3);

        return [
            'title' => $judul,
            'slug' => Str::slug($judul).'-'.Str::random(6),
            'description' => $this->faker->sentence(12),
            // Gambar wajib, di basis data maupun di formulirnya: karya yang
            // ditampilkan di beranda tanpa gambar tidak ada gunanya.
            'image' => 'featured-works/'.Str::random(12).'.jpg',
            'category' => 'design',
            'client' => 'HIMATIF',
            'project_date' => now()->subMonth(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
