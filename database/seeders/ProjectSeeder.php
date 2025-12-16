<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
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

        Project::factory()
            ->count(15)
            ->create([
                'created_by' => fn () => $users->random()->id,
            ])
            ->each(function (Project $project) use ($tags) {
                $project->tags()->attach(
                    $tags->random(rand(1, 4))->pluck('id')->toArray()
                );
            });
    }
}
