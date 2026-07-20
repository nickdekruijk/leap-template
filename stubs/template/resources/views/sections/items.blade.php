{{--
    A card row for a listed content type, fed from the model rather than typed by hand.
    Every content type renders through this view (the Page resource points each index
    section at it via the view convention).

    A teaser row (limited) previews the overview and links to it. A full list — the
    overview itself, and tag-fixed pages — lists everything and gets the tag filter, its
    chips drawn from the tags the shown items carry. For an event type the section's
    `period` decides upcoming (default), past, or both (upcoming first, then past).
--}}
@php
    use App\Http\Controllers\PageController;

    $type = $section['_name'];
    $model = PageController::indexModel($type);
    $limited = ! empty($section['limit']);
    $fixedTag = $section['tag'] ?? null;
    $period = ($model && method_exists($model, 'scopeFuture')) ? ($section['period'] ?? 'upcoming') : null;

    $items = $limited
        ? PageController::sectionItems($section)
        : PageController::items($type, $fixedTag, withTags: true, period: $period);

    // The tag filter belongs to a full overview, not to a teaser: filtering six cards
    // out of a row that already shows a selection says nothing. It follows the limit
    // rather than the layout for that reason — a limited grid is still a teaser.
    $tags = $limited ? null : PageController::sectionTags($items, $fixedTag);

    // Layout used to be read off the limit. A section saved before there was a field
    // for it has no such key, so the old rule is what it falls back to and nothing
    // changes appearance on its own.
    $layout = $section['layout'] ?? ($limited ? 'horizontal' : 'grid');

    // A teaser points at the overview it is a preview of. The editor can name that link
    // themselves; without one, the page listing this type is where it goes. A full
    // overview gets nothing — it would link to itself.
    $link = ($section['link'] ?? null) ?: ($limited ? PageController::overviewUrl($type) : null);
    $linkLabel = ($section['link_label'] ?? null) ?: ($link ? __('View all') : null);
@endphp

<x-items-section
    layout="items-{{ $layout }}"
    :columns="$section['columns'] ?? null"
    :head="$section->head ?? null"
    :link="$link"
    :link-label="$linkLabel"
    :items="$items"
    :tags="$tags"
    :filter="! $limited"
/>
