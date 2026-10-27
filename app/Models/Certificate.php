<?php

namespace App\Models;

use App\Enums\CertificateDesign;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Certificate extends Model
{
    use HasFactory;
    use RecordsActivity;

    protected $fillable = [
        'certificate_event_id', 'participant_id', 'event_participant_id', 'certificate_number',
        'verification_token', 'recipient_name', 'recipient_email', 'recipient_role',
        'recipient_role_label', 'variables', 'issued_at', 'revoked_at', 'revocation_reason',
        'emailed_at', 'email_failed_at', 'email_error',
        'design_source', 'design_file_path', 'design_uploaded_at',
    ];

    protected $attributes = [
        'design_source' => 'template',
    ];

    protected $casts = [
        'variables' => 'array',
        'issued_at' => 'datetime',
        'revoked_at' => 'datetime',
        'emailed_at' => 'datetime',
        'email_failed_at' => 'datetime',
        'design_source' => CertificateDesign::class,
        'design_uploaded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Certificate $certificate) {
            $certificate->verification_token ??= (string) Str::uuid();
            $certificate->issued_at ??= now();
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(CertificateEvent::class, 'certificate_event_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(CertificateEventParticipant::class, 'event_participant_id');
    }

    /**
     * Satu-satunya definisi validitas sertifikat: sudah terbit, belum dicabut,
     * dan kegiatannya sudah dipublikasikan.
     */
    public function isValid(): bool
    {
        return $this->issued_at !== null
            && $this->revoked_at === null
            && $this->event?->isPublished() === true;
    }

    public function isOwnDesign(): bool
    {
        return $this->design_source === CertificateDesign::EXTERNAL;
    }

    /**
     * Sudah ada dokumen yang bisa diunduh: PDF dari template selalu bisa
     * dibentuk, sedangkan sertifikat desain sendiri baru punya dokumen setelah
     * berkas jadinya diunggah. Sebelum itu ia tetap sah dan bisa diverifikasi.
     */
    public function hasDocument(): bool
    {
        return ! $this->isOwnDesign() || filled($this->design_file_path);
    }

    /**
     * Sertifikat yang boleh diunduh dan dikirimkan kepada penerimanya.
     */
    public function isDeliverable(): bool
    {
        return $this->isValid() && $this->hasDocument();
    }

    /**
     * Email hanya layak dikirim untuk sertifikat yang bisa diunduh, punya
     * alamat tujuan, dan belum pernah terkirim.
     */
    public function awaitsEmail(): bool
    {
        return $this->emailed_at === null && filled($this->recipient_email) && $this->isDeliverable();
    }

    public function scopeNotYetEmailed(Builder $query): Builder
    {
        return $query->whereNull('emailed_at')->whereNotNull('recipient_email')->whereNull('revoked_at')->withDocument();
    }

    /**
     * Bentuk kueri dari hasDocument().
     */
    public function scopeWithDocument(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('design_source', '!=', CertificateDesign::EXTERNAL->value)
            ->orWhereNotNull('design_file_path'));
    }

    /**
     * Sertifikat desain sendiri yang berkas jadinya belum diunggah.
     */
    public function scopeAwaitingDesign(Builder $query): Builder
    {
        return $query->where('design_source', CertificateDesign::EXTERNAL->value)->whereNull('design_file_path');
    }

    public function downloadUrl(): string
    {
        return route('certificates.download', $this->verification_token);
    }

    /**
     * Nama berkas unduhan.
     *
     * Nomor resmi kampus memuat garis miring — "0001/CERT/MSC-JGU/IX/2026" —
     * dan nama berkas tidak boleh memuatnya: header Content-Disposition
     * menolaknya, sehingga setiap unduhan berujung galat. Karena itu nomornya
     * diratakan lebih dulu, tetap terbaca tanpa memakai pemisah jalur.
     */
    public function downloadFileName(): string
    {
        $nomor = self::flattenNumber($this->certificate_number);

        // Berkas desain sendiri bisa berupa PDF maupun gambar.
        $ekstensi = $this->isOwnDesign() && filled($this->design_file_path)
            ? strtolower(pathinfo($this->design_file_path, PATHINFO_EXTENSION))
            : 'pdf';

        return 'Sertifikat-'.($nomor !== '' ? $nomor : $this->verification_token).'.'.$ekstensi;
    }

    /**
     * Nomor sertifikat tanpa garis miring dan tanda lain yang tidak boleh ada
     * di nama berkas, misalnya "0001-CERT-MSC-JGU-IX-2026".
     */
    public static function flattenNumber(?string $nomor): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', (string) $nomor), '-');
    }

    public function verificationUrl(): string
    {
        return route('certificates.verify', $this->verification_token);
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
        return ['certificate_number', 'recipient_name', 'recipient_email', 'revoked_at', 'revocation_reason', 'design_file_path'];
    }
}
