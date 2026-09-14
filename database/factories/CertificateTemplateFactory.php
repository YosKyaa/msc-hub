<?php

namespace Database\Factories;

use App\Models\CertificateTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificateTemplateFactory extends Factory
{
    protected $model = CertificateTemplate::class;

    public function definition(): array
    {
        return [
            'name' => 'Template '.fake()->words(2, true),
            'background_path' => 'certificates/templates/dummy.png',
            'canvas_width' => 1123,
            'canvas_height' => 794,
            'elements' => [],
            'fonts' => [],
            'is_active' => true,
        ];
    }
}
