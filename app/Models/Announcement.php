<?php

namespace App\Models;

use App\Enums\AnnouncementCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'content',
        'image',
        'category',
        'published_at',
        'is_pinned',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'category' => AnnouncementCategory::class,
        'published_at' => 'datetime',
        'is_pinned' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Announcement $announcement) {
            if (empty($announcement->slug)) {
                $announcement->slug = static::generateUniqueSlug($announcement->title);
            }
            if (empty($announcement->created_by) && auth()->check()) {
                $announcement->created_by = auth()->id();
            }
        });

        static::updating(function (Announcement $announcement) {
            if (auth()->check()) {
                $announcement->updated_by = auth()->id();
            }
        });
    }

    public static function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeVisible($query)
    {
        return $query->active()->published();
    }

    public function isPublished(): bool
    {
        return $this->published_at && $this->published_at->lte(now());
    }

    public function isDraft(): bool
    {
        return !$this->published_at;
    }
}
