<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'background_path', 'canvas_width', 'canvas_height', 'elements', 'fonts', 'is_active', 'created_by'];
    protected $casts = ['elements' => 'array', 'fonts' => 'array', 'is_active' => 'boolean'];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function events(): HasMany { return $this->hasMany(CertificateEvent::class); }
}
