<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participant extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'institutional_id', 'name', 'google_display_name', 'email', 'phone',
        'institution', 'faculty', 'study_program', 'source', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function participations(): HasMany { return $this->hasMany(CertificateEventParticipant::class); }
    public function certificates(): HasMany { return $this->hasMany(Certificate::class); }

    /**
     * Email selalu disimpan dalam bentuk ternormalisasi karena dipakai
     * sebagai kunci dedup peserta.
     */
    public function setEmailAttribute(?string $value): void
    {
        $normalised = mb_strtolower(trim((string) $value));

        $this->attributes['email'] = $normalised === '' ? null : $normalised;
    }
}
