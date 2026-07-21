{{--
    The breadcrumb trail: the homepage as a house icon, every ancestor, and the current
    page — that last one plain text, since it is where the visitor already stands.

    Rendered for any page whose trail is longer than a single step, which leaves the
    homepage without one, and only while the owning page has its breadcrumb switched on.
    The trail comes from PageController::breadcrumbs(); a content item passes the
    overview page it hangs under as $parent.

    The very same trail feeds the BreadcrumbList JSON-LD, so what a search engine reads
    and what a visitor sees cannot drift apart.
--}}
@props(['page' => null, 'parent' => null])

@php
    $crumbs = App\Http\Controllers\PageController::breadcrumbs($page, $parent);
@endphp

@if ($crumbs->count() > 1)
    @php
        // "Back" is the crumb before the current one. It has no URL when that ancestor is
        // untranslated in this locale — nothing to link to, so no button either.
        $back = $crumbs->slice(-2, 1)->first()['url'];

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $crumbs->values()->map(fn (array $crumb, int $i): array => array_filter([
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['title'],
                // The last crumb has no link of its own; it is the page being looked at.
                'item' => $crumb['url'] ? url($crumb['url']) : ($i === $crumbs->count() - 1 ? url()->current() : null),
            ]))->all(),
        ];
    @endphp

    <nav class="breadcrumbs" aria-label="{{ __('Breadcrumb') }}">
        <div class="main-width">
            @if ($back)
                <a class="breadcrumbs-back" href="{{ $back }}" rel="up" x-data="breadcrumbBack" x-on:click="back($event)">
                    <x-fas-arrow-left aria-hidden="true" />{{ __('Back') }}
                </a>
            @endif

            <ol>
                @foreach ($crumbs as $crumb)
                    <li>
                        @if ($crumb['home'] && $crumb['url'])
                            <a href="{{ $crumb['url'] }}" title="{{ $crumb['title'] }}">
                                <x-fas-house aria-hidden="true" />
                                <span class="visually-hidden">{{ $crumb['title'] }}</span>
                            </a>
                        @elseif ($crumb['url'])
                            <a href="{{ $crumb['url'] }}">{{ $crumb['title'] }}</a>
                        @elseif ($loop->last)
                            <span aria-current="page">{{ $crumb['title'] }}</span>
                        @else
                            {{-- An ancestor with no slug in this locale: worth naming, not worth linking. --}}
                            <span>{{ $crumb['title'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    </nav>

    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
