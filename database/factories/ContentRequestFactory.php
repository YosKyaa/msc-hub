<?php

namespace Database\Factories;

use App\Models\ContentRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContentRequest>
 */
class ContentRequestFactory extends Factory
{
    protected $model = ContentRequest::class;

    public function definition(): array
    {
        return [
            'request_code' => 'CR-'.Str::upper(Str::random(8)),
            'requester_name' => $this->faker->name(),
            'requester_email' => $this->faker->unique()->safeEmail(),
            'requester_type' => 'student',
            'unit' => 'HIMATIF',
            'content_type' => 'design_poster',
            'purpose' => $this->faker->sentence(8),
            'deadline' => now()->addWeek(),
            'status' => 'incoming',
        ];
    }
}
