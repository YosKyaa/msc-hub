<?php

namespace App\Models;

use App\Enums\EligibilityRule;
use App\Enums\ParticipantRole;
use App\Enums\ParticipantSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CertificateEventParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_event_id', 'participant_id', 'role', 'role_label',
        'attendance_status', 'eligible_at', 'variables',
        'checked_in_at', 'checked_out_at', 'source', 'certificate_number',
    ];

    protected $casts = [
        'eligible_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'variables' => 'array',
        'source' => ParticipantSource::class,
    ];

    public function event(): BelongsTo { return $this->belongsTo(CertificateEvent::class, 'certificate_event_id'); }
    public function participant(): BelongsTo { return $this->belongsTo(Participant::class); }
    public function certificate(): HasOne { return $this->hasOne(Certificate::class, 'event_participant_id'); }

    public function scopeEligible(Builder $query): Builder
    {
        return $query->whereNotNull('eligible_at');
    }

    public function scopeWithoutCertificate(Builder $query): Builder
    {
        return $query->whereDoesntHave('certificate');
    }

    public function isEligible(): bool
    {
        return $this->eligible_at !== null;
    }

    public function resolvedRoleLabel(): string
    {
        return $this->role_label
            ?: (ParticipantRole::tryFrom((string) $this->role) ?? ParticipantRole::PARTICIPANT)->getLabel();
    }

    /**
     * Satu-satunya tempat aturan kelayakan dievaluasi (dipakai controller absensi,
     * panel Filament, dan job penerbitan). Aturan `manual` tidak pernah mengubah
     * flag secara otomatis — admin tetap berkuasa penuh.
     */
    public function syncEligibility(): bool
    {
        $rule = $this->event?->eligibilityRule() ?? EligibilityRule::MANUAL;

        if ($rule === EligibilityRule::MANUAL) {
            return $this->isEligible();
        }

        $qualifies = match ($rule) {
            EligibilityRule::CHECKIN_ONLY => $this->checked_in_at !== null,
            EligibilityRule::CHECKIN_AND_CHECKOUT => $this->checked_in_at !== null && $this->checked_out_at !== null,
            default => false,
        };

        if ($qualifies && ! $this->isEligible()) {
            $this->forceFill([
                'eligible_at' => now(),
                'attendance_status' => 'attended',
            ])->save();
        }

        return $this->isEligible();
    }
}
