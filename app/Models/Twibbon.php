<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\TwibbonFrame;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bingkai twibbon yang dipasang peserta di atas fotonya sendiri.
 *
 * Foto peserta diolah di perangkatnya dan tidak pernah dikirim ke server;
 * yang tersimpan di sini hanya bingkainya dan berapa kali dipakai.
 */
class Twibbon extends Model
{
    use HasFactory;
    use RecordsActivity;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_OPEN = 'open';

    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'title', 'slug', 'description', 'caption', 'frame_path',
        'is_published', 'starts_at', 'ends_at', 'created_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'uses_count' => 'integer',
        'frame_width' => 'integer',
        'frame_height' => 'integer',
        'transparent_ratio' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function (Twibbon $twibbon): void {
            if (blank($twibbon->slug)) {
                $twibbon->slug = static::uniqueSlug($twibbon->title);
            }

            // Ukuran bingkai dihitung sekali saat disimpan: halaman publik
            // membutuhkannya untuk kanvas, dan membacanya dari berkas setiap
            // kali halaman dibuka hanya membuang waktu.
            if ($twibbon->isDirty('frame_path') && filled($twibbon->frame_path)
                && Storage::disk('public')->exists($twibbon->frame_path)) {
                $bingkai = TwibbonFrame::inspect(Storage::disk('public')->path($twibbon->frame_path));

                $twibbon->frame_width = $bingkai?->width ?? 0;
                $twibbon->frame_height = $bingkai?->height ?? 0;
                $twibbon->transparent_ratio = $bingkai?->transparentPercent;
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Terbit dan sedang dalam periodenya.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function status(): string
    {
        return match (true) {
            ! $this->is_published => self::STATUS_DRAFT,
            $this->starts_at !== null && $this->starts_at->isFuture() => self::STATUS_SCHEDULED,
            $this->ends_at !== null && $this->ends_at->isPast() => self::STATUS_ENDED,
            default => self::STATUS_OPEN,
        };
    }

    public function isOpen(): bool
    {
        return $this->status() === self::STATUS_OPEN;
    }

    public function publicUrl(): string
    {
        return route('twibbon.show', $this->slug);
    }

    /**
     * Alamat bingkai tanpa domain.
     *
     * Kanvas peramban menolak mengekspor gambar yang memuat berkas dari asal
     * lain. Alamat absolut dibentuk dari APP_URL, yang bisa berbeda dari
     * alamat yang sedang dibuka peserta (http lawan https, dengan atau tanpa
     * www) — dan tombol unduh lalu gagal tanpa sebab yang terlihat. Alamat
     * tanpa domain selalu seasal dengan halamannya.
     */
    public function frameRelativeUrl(): string
    {
        return (string) parse_url(Storage::disk('public')->url($this->frame_path), PHP_URL_PATH);
    }

    public function frameAbsoluteUrl(): string
    {
        return Storage::disk('public')->url($this->frame_path);
    }

    public static function uniqueSlug(string $judul, ?int $kecuali = null): string
    {
        $dasar = Str::slug($judul) ?: 'twibbon';
        $slug = $dasar;
        $urutan = 2;

        while (static::where('slug', $slug)->when($kecuali, fn (Builder $q) => $q->whereKeyNot($kecuali))->exists()) {
            $slug = $dasar.'-'.$urutan++;
        }

        return $slug;
    }

    /**
     * @return list<string>
     */
    protected static function auditedAttributes(): array
    {
        return ['title', 'slug', 'caption', 'frame_path', 'is_published', 'starts_at', 'ends_at'];
    }
}
