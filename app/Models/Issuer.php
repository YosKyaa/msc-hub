<?php

namespace App\Models;

use App\Enums\CertificateNumberReset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Pihak yang menerbitkan sertifikat.
 *
 * MSC JGU adalah penerbit rumah; kegiatan yang tidak menyebut penerbit
 * otomatis memakainya. Penerbit lain membawa brand dan penomorannya sendiri
 * sehingga sertifikat mitra tidak tampil sebagai terbitan JGU dan tidak
 * menggerus urutan nomor JGU.
 */
class Issuer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'logo_path', 'address_line', 'verification_note',
        'number_pattern', 'number_reset', 'number_start', 'is_house', 'is_active',
    ];

    protected $casts = [
        'is_house' => 'boolean',
        'is_active' => 'boolean',
        'number_reset' => CertificateNumberReset::class,
        'number_start' => 'integer',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(CertificateEvent::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Penerbit bawaan untuk kegiatan yang tidak menyebut penerbit.
     */
    public static function house(): ?self
    {
        return static::query()->where('is_house', true)->orderBy('id')->first();
    }

    /**
     * Nomor urut pertama setiap kali urutan dimulai ulang.
     *
     * Unit penerbit sering sudah memegang register sendiri yang berjalan di
     * luar sistem ini, sehingga memaksa urutannya mulai dari satu akan
     * bertabrakan dengan nomor yang sudah terpakai di sana.
     */
    public function startingNumber(): int
    {
        return max(1, (int) ($this->number_start ?: 1));
    }

    public function logoUrl(): string
    {
        return filled($this->logo_path)
            ? Storage::disk('public')->url($this->logo_path)
            : asset('img/jgu.png');
    }

    /**
     * Kalimat penjamin di halaman verifikasi. Untuk penerbit di luar JGU,
     * peran MSC disebut terpisah agar tidak terbaca sebagai terbitan JGU.
     */
    public function verificationStatement(): string
    {
        if (filled($this->verification_note)) {
            return $this->verification_note;
        }

        return $this->is_house
            ? "Data sertifikat ini tercatat dan diterbitkan oleh {$this->name}."
            : "Data sertifikat ini tercatat dan diterbitkan oleh {$this->name}, "
                .'difasilitasi Media & Strategic Communications Jakarta Global University.';
    }
}
