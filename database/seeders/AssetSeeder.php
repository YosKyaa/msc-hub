<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        if ($users->isEmpty()) {
            $this->call(UserSeeder::class);
            $users = User::all();
        }

        $tags = Tag::all();
        if ($tags->isEmpty()) {
            $this->call(TagSeeder::class);
            $tags = Tag::all();
        }

        $projects = Project::all();
        if ($projects->isEmpty()) {
            $this->call(ProjectSeeder::class);
            $projects = Project::all();
        }

        Asset::factory()
            ->count(50)
            ->create([
                'created_by' => fn () => $users->random()->id,
                'pic_user_id' => fn () => fake()->boolean(70) ? $users->random()->id : null,
                'project_id' => fn () => fake()->boolean(70) ? $projects->random()->id : null,
            ])
            ->each(function (Asset $asset) use ($tags) {
                $asset->tags()->attach(
                    $tags->random(rand(1, 5))->pluck('id')->toArray()
                );
            });
    }
}
