<?php

namespace Database\Factories;

use App\Enums\ParticipantRole;
use App\Enums\ParticipantSource;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificateEventParticipantFactory extends Factory
{
    protected $model = CertificateEventParticipant::class;

    public function definition(): array
    {
        return [
            'certificate_event_id' => CertificateEvent::factory(),
            'participant_id' => Participant::factory(),
            'role' => ParticipantRole::PARTICIPANT->value,
            'attendance_status' => 'registered',
            'source' => ParticipantSource::MANUAL->value,
        ];
    }

    public function eligible(): static
    {
        return $this->state([
            'attendance_status' => 'attended',
            'eligible_at' => now(),
        ]);
    }
}
