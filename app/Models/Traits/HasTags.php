<?php

namespace App\Models\Traits;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withTimestamps();
    }

    public function syncTags(array $tagIds): void
    {
        $this->tags()->sync($tagIds);
    }

    public function attachTag(int|Tag $tag): void
    {
        $tagId = $tag instanceof Tag ? $tag->id : $tag;
        $this->tags()->syncWithoutDetaching([$tagId]);
    }

    public function detachTag(int|Tag $tag): void
    {
        $tagId = $tag instanceof Tag ? $tag->id : $tag;
        $this->tags()->detach($tagId);
    }

    public function hasTag(int|Tag|string $tag): bool
    {
        if (is_string($tag)) {
            return $this->tags()->where('name', $tag)->exists();
        }

        $tagId = $tag instanceof Tag ? $tag->id : $tag;
        return $this->tags()->where('tags.id', $tagId)->exists();
    }
}
