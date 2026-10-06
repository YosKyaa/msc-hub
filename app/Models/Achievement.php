<?php

namespace App\Models;

use App\Enums\AchievementCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu prestasi MSC: penghargaan, sertifikat, atau piala.
 */
class Achievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'awarded_by',
        'category',
        'level',
        'description',
        'image',
        'achieved_at',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => AchievementCategory::class,
            'achieved_at' => 'date',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Urutan tampil di beranda.
     *
     * Admin memegang kendali lewat sort_order; yang tidak diatur jatuh ke
     * urutan waktu, prestasi terbaru lebih dulu. Tanpa penyeimbang kedua ini,
     * seluruh prestasi yang dibiarkan pada nilai bawaan akan tampil dalam
     * urutan acak menurut id.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('achieved_at')->orderByDesc('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Tahun pencapaiannya, untuk ditampilkan ringkas di kartu beranda.
     */
    public function year(): ?string
    {
        return $this->achieved_at?->format('Y');
    }
}
