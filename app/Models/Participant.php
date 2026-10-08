<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Services\Certificates\ParticipantRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participant extends Model
{
    use HasFactory;
    use RecordsActivity;

    protected $fillable = [
        'type', 'institutional_id', 'name', 'google_display_name', 'email', 'phone',
        'institution', 'faculty', 'study_program', 'source', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function participations(): HasMany { return $this->hasMany(CertificateEventParticipant::class); }
    public function certificates(): HasMany { return $this->hasMany(Certificate::class); }

    /**
     * Email selalu disimpan dalam bentuk ternormalisasi karena dipakai
     * sebagai kunci dedup peserta. Aturannya tinggal di ParticipantRegistry,
     * supaya jalur mana pun yang menyimpan email sampai pada bentuk yang sama.
     */
    public function setEmailAttribute(?string $value): void
    {
        $normalised = ParticipantRegistry::normaliseEmail($value);

        $this->attributes['email'] = $normalised === '' ? null : $normalised;
    }

    /**
     * Pembuatannya tidak dicatat: baris ini dibuat massal lewat impor, penerbitan,
     * atau formulir publik, dan satu impor tidak boleh membanjiri jejak audit.
     */
    protected static $recordEvents = ['updated', 'deleted'];

    /**
     * @return list<string>
     */
    protected static function auditedAttributes(): array
    {
        return ['name', 'email', 'institutional_id', 'study_program'];
    }
}
