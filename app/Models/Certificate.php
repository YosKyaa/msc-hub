<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = ['certificate_event_id', 'participant_id', 'event_participant_id', 'certificate_number', 'verification_token', 'recipient_name', 'recipient_email', 'recipient_role', 'recipient_role_label', 'variables', 'issued_at', 'revoked_at', 'revocation_reason'];
    protected $casts = ['variables' => 'array', 'issued_at' => 'datetime', 'revoked_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (Certificate $certificate) {
            $certificate->verification_token ??= (string) Str::uuid();
            $certificate->issued_at ??= now();
        });
    }

    public function event(): BelongsTo { return $this->belongsTo(CertificateEvent::class, 'certificate_event_id'); }
    public function participant(): BelongsTo { return $this->belongsTo(Participant::class); }
    public function participation(): BelongsTo { return $this->belongsTo(CertificateEventParticipant::class, 'event_participant_id'); }
    public function isValid(): bool { return $this->issued_at !== null && $this->revoked_at === null && $this->event->status === 'published'; }
}
