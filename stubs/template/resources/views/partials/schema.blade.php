{{-- JSON-LD structured data for a content detail page. Type is derived from the
     content archetype: an event → Event, a news key → NewsArticle, else CreativeWork.
     Takes $item and $type. The BreadcrumbList belongs to the trail that is actually
     shown, so it is emitted by components/breadcrumbs.blade.php instead. --}}
@php
    $isEvent = method_exists($item::class, 'scopeFuture');
    $schemaType = $isEvent ? 'Event' : (Str::contains($type, 'news') ? 'NewsArticle' : 'CreativeWork');

    $data = array_filter([
        '@context' => 'https://schema.org',
        '@type' => $schemaType,
        'name' => $item->title,
        'headline' => $item->title,
        'url' => url()->current(),
        'description' => $item->metaDescription() ?: null,
        'image' => $item->media->first()?->url ?? null,
        'datePublished' => (! $isEvent && ! empty($item->published_at)) ? $item->published_at->toIso8601String() : null,
        'startDate' => ($isEvent && ! empty($item->date)) ? $item->date->toDateString() : null,
        'endDate' => ($isEvent && ! empty($item->ends_at)) ? $item->ends_at->toIso8601String() : null,
    ]);
@endphp
<script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
