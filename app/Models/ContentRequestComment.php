<?php

namespace App\Models;

use App\Enums\CommentAuthorType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentRequestComment extends Model
{
    protected $fillable = [
        'content_request_id',
        'author_type',
        'author_name',
        'author_email',
        'user_id',
        'message',
    ];

    protected $casts = [
        'author_type' => CommentAuthorType::class,
    ];

    public function contentRequest(): BelongsTo
    {
        return $this->belongsTo(ContentRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->user) {
            return $this->user->name;
        }
        return $this->author_name ?? $this->author_email ?? 'Unknown';
    }
}
