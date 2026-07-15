{{-- JSON-LD structured data for a content detail page. Type is derived from the
     content archetype: an event → Event, a news key → NewsArticle, else CreativeWork.
     Plus a BreadcrumbList (overview → item). $item, $type, $parent, $parentUrl. --}}
@php
    $isEvent = method_exists($item::class, 'scopeFuture');
    $schemaType = $isEvent ? 'Event' : (Str::contains($type, 'news') ? 'NewsArticle' : 'CreativeWork');

    $data = array_filter([
        '@context' => 'https://schema.org',
        '@type' => $schemaType,
        'name' => $item->title,
        'headline' => $item->title,
        'url' => url()->current(),
        'description' => filled($item->intro ?? null) ? $item->intro : null,
        'image' => $item->media->first()?->url ?? null,
        'datePublished' => (! $isEvent && ! empty($item->published_at)) ? $item->published_at->toIso8601String() : null,
        'startDate' => ($isEvent && ! empty($item->date)) ? $item->date->toDateString() : null,
        'endDate' => ($isEvent && ! empty($item->ends_at)) ? $item->ends_at->toIso8601String() : null,
    ]);

    $breadcrumb = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => $parent->title, 'item' => url($parentUrl)],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $item->title, 'item' => url()->current()],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/ld+json">{!! json_encode($breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
