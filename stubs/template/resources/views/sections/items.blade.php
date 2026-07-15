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

    $tags = $limited ? null : PageController::sectionTags($items, $fixedTag);
@endphp

<x-items-section
    layout="{{ $limited ? 'items-horizontal' : 'items-grid' }}"
    :head="$section->head ?? null"
    :link="$section->link ?? null"
    :link-label="$section->link_label ?? null"
    :items="$items"
    :tags="$tags"
    :filter="! $limited"
/>
