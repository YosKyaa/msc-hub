<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Models\Traits\HasTags;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes, HasTags;

    protected $fillable = [
        'title',
        'description',
        'unit',
        'event_date',
        'location',
        'status',
        'created_by',
    ];

    protected $casts = [
        'event_date' => 'date',
        'status' => ProjectStatus::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (empty($project->created_by) && auth()->check()) {
                $project->created_by = auth()->id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function getYearAttribute(): ?int
    {
        return $this->event_date?->year;
    }
}
