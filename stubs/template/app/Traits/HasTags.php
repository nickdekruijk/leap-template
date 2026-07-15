<?php

namespace App\Traits;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * A content type that can be labelled with shared Tags — projects, news, events,
 * whatever the site lists. The tags are polymorphic (one vocabulary, many types) and
 * drive the filter chips above an overview.
 *
 * Left off a model when the site is installed with --no-tags; the Tag model then does
 * not exist and this trait is not used.
 */
trait HasTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->orderBy('sort');
    }

    /**
     * Only the records carrying a given tag — what a tag-filtered overview narrows to.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeTagged(Builder $query, Tag|int $tag): Builder
    {
        return $query->whereHas('tags', fn (Builder $tags) => $tags->whereKey($tag));
    }
}
