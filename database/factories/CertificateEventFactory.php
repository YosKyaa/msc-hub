<?php

namespace Database\Factories;

use App\Enums\EligibilityRule;
use App\Models\CertificateEvent;
use App\Models\CertificateTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CertificateEventFactory extends Factory
{
    protected $model = CertificateEvent::class;

    public function definition(): array
    {
        $name = 'Kegiatan '.fake()->unique()->words(3, true);

        return [
            'certificate_template_id' => CertificateTemplate::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'event_date' => now()->toDateString(),
            'organizer' => 'Media & Strategic Communications',
            'status' => 'draft',
            'attendance_enabled' => false,
            'eligibility_rule' => EligibilityRule::MANUAL->value,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => 'published']);
    }

    /**
     * Kegiatan dengan absensi aktif dan window yang sedang terbuka.
     */
    public function withOpenAttendance(EligibilityRule $rule = EligibilityRule::CHECKIN_ONLY): static
    {
        return $this->state([
            'attendance_enabled' => true,
            'attendance_open_at' => now()->subHour(),
            'attendance_close_at' => now()->addHour(),
            'eligibility_rule' => $rule->value,
        ]);
    }
}
