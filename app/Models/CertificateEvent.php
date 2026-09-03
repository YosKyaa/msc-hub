<?php

namespace App\Models;

use App\Enums\AttendanceAction;
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
        'attendance_enabled', 'eligibility_rule',
        'checkin_token', 'checkin_open_at', 'checkin_close_at',
        'checkout_token', 'checkout_open_at', 'checkout_close_at',
    ];

    protected $casts = [
        'event_date' => 'date',
        'attendance_enabled' => 'boolean',
        'checkin_open_at' => 'datetime',
        'checkin_close_at' => 'datetime',
        'checkout_open_at' => 'datetime',
        'checkout_close_at' => 'datetime',
        'eligibility_rule' => EligibilityRule::class,
    ];

    protected static function booted(): void
    {
        // Kedua token dibuat sekali saat absensi diaktifkan dan tidak pernah
        // diganti, supaya QR yang sudah dicetak tetap berlaku.
        static::saving(function (CertificateEvent $event) {
            if (! $event->attendance_enabled) {
                return;
            }

            foreach (AttendanceAction::cases() as $action) {
                if (blank($event->{$action->tokenColumn()})) {
                    $event->{$action->tokenColumn()} = (string) Str::ulid();
                }
            }
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function participations(): HasMany
    {
        return $this->hasMany(CertificateEventParticipant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function attendanceToken(AttendanceAction $action): ?string
    {
        return $this->{$action->tokenColumn()};
    }

    public function attendanceUrl(AttendanceAction $action): ?string
    {
        $token = $this->attendanceToken($action);

        return $token ? route('attendance.show', ['action' => $action->value, 'token' => $token]) : null;
    }

    /**
     * Setiap aksi punya window sendiri. Batas yang dikosongkan berarti tidak
     * dibatasi dari sisi itu.
     */
    public function attendanceWindowState(AttendanceAction $action, ?Carbon $moment = null): string
    {
        $moment ??= now();
        $opensAt = $this->{$action->openColumn()};
        $closesAt = $this->{$action->closeColumn()};

        if (! $this->attendance_enabled) {
            return 'disabled';
        }

        if ($opensAt && $moment->lt($opensAt)) {
            return 'not_started';
        }

        if ($closesAt && $moment->gt($closesAt)) {
            return 'closed';
        }

        return 'open';
    }

    public function attendanceIsOpen(AttendanceAction $action, ?Carbon $moment = null): bool
    {
        return $this->attendanceWindowState($action, $moment) === 'open';
    }

    public function eligibilityRule(): EligibilityRule
    {
        return $this->eligibility_rule ?? EligibilityRule::MANUAL;
    }
}
