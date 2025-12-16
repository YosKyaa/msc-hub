<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\Platform;
use App\Models\Traits\HasTags;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes, HasTags;

    protected $fillable = [
        'project_id',
        'title',
        'asset_type',
        'platform',
        'source_link',
        'output_link',
        'happened_at',
        'year',
        'status',
        'pic_user_id',
        'created_by',
        'notes',
        'is_featured',
    ];

    protected $casts = [
        'happened_at' => 'date',
        'asset_type' => AssetType::class,
        'platform' => Platform::class,
        'status' => AssetStatus::class,
        'is_featured' => 'boolean',
        'year' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Asset $asset) {
            if (empty($asset->created_by) && auth()->check()) {
                $asset->created_by = auth()->id();
            }
            if ($asset->happened_at && empty($asset->year)) {
                $asset->year = $asset->happened_at->year;
            }
        });

        static::updating(function (Asset $asset) {
            if ($asset->isDirty('happened_at') && $asset->happened_at) {
                $asset->year = $asset->happened_at->year;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getPrimaryLinkAttribute(): ?string
    {
        return $this->output_link ?? $this->source_link;
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopePublished($query)
    {
        return $query->where('status', AssetStatus::Published);
    }

    public function scopeByYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeByType($query, AssetType $type)
    {
        return $query->where('asset_type', $type);
    }

    public function scopeByPlatform($query, Platform $platform)
    {
        return $query->where('platform', $platform);
    }
}
