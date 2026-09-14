<?php

namespace Database\Factories;

use App\Enums\ParticipantRole;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'certificate_event_id' => CertificateEvent::factory(),
            'certificate_number' => 'CERT-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'verification_token' => (string) Str::uuid(),
            'recipient_name' => fake()->name(),
            'recipient_email' => fake()->unique()->safeEmail(),
            'recipient_role' => ParticipantRole::PARTICIPANT->value,
            'recipient_role_label' => ParticipantRole::PARTICIPANT->getLabel(),
            'issued_at' => now(),
        ];
    }
}
