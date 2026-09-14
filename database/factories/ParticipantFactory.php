<?php

namespace Database\Factories;

use App\Models\Participant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParticipantFactory extends Factory
{
    protected $model = Participant::class;

    public function definition(): array
    {
        $name = fake()->name();

        return [
            'type' => 'student',
            'name' => $name,
            'google_display_name' => $name,
            'email' => fake()->unique()->userName().'@student.jgu.ac.id',
            'institution' => 'Jakarta Global University',
            'source' => 'admin',
        ];
    }
}
