<?php

namespace App\Models;

use App\Enums\EligibilityRule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CertificateEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_template_id', 'name', 'slug', 'event_date', 'organizer',
        'signatory_name', 'signatory_title', 'status', 'created_by',
        'attendance_enabled', 'attendance_token', 'attendance_open_at',
        'attendance_close_at', 'eligibility_rule',
    ];

    protected $casts = [
        'event_date' => 'date',
        'attendance_enabled' => 'boolean',
        'attendance_open_at' => 'datetime',
        'attendance_close_at' => 'datetime',
        'eligibility_rule' => EligibilityRule::class,
    ];

    protected static function booted(): void
    {
        // Token dibuat sekali saat absensi diaktifkan dan tidak pernah diganti,
        // supaya QR yang sudah dicetak tetap berlaku.
        static::saving(function (CertificateEvent $event) {
            if ($event->attendance_enabled && blank($event->attendance_token)) {
                $event->attendance_token = (string) Str::ulid();
            }
        });
    }

    public function template(): BelongsTo { return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id'); }
    public function certificates(): HasMany { return $this->hasMany(Certificate::class); }
    public function participations(): HasMany { return $this->hasMany(CertificateEventParticipant::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function attendanceUrl(): ?string
    {
        return $this->attendance_token ? route('attendance.show', $this->attendance_token) : null;
    }

    /**
     * Absensi hanya dilayani ketika fitur aktif dan waktu berada di dalam window.
     * Batas yang dikosongkan berarti tidak dibatasi dari sisi itu.
     */
    public function attendanceWindowState(?Carbon $moment = null): string
    {
        $moment ??= now();

        if (! $this->attendance_enabled) {
            return 'disabled';
        }

        if ($this->attendance_open_at && $moment->lt($this->attendance_open_at)) {
            return 'not_started';
        }

        if ($this->attendance_close_at && $moment->gt($this->attendance_close_at)) {
            return 'closed';
        }

        return 'open';
    }

    public function attendanceIsOpen(?Carbon $moment = null): bool
    {
        return $this->attendanceWindowState($moment) === 'open';
    }

    public function eligibilityRule(): EligibilityRule
    {
        return $this->eligibility_rule ?? EligibilityRule::MANUAL;
    }
}
