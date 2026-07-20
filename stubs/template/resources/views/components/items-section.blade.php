{{--
    A row or grid of content cards (news, events, projects, whatever the site lists).
    Fed pre-resolved $items by sections/items.blade.php. One home for the card markup:
    the whole card is a single link, cards fill their row's height, and — when a
    filterable overview passes $tags — a chip row filters them client-side.

    An item without a URL (its type has no overview page) renders as a plain card, not a
    link. A card shows its date/time line only when the item carries a `date`.
--}}
@props([
    'items',
    'layout' => 'items-grid',
    'columns' => null,
    'head' => null,
    'link' => null,
    'linkLabel' => null,
    'tags' => null,
    'filter' => false,
])

@php
    // How many cards stand side by side, when the section overrides the site's own
    // setting. All three sizes are written out because grid-template-columns takes a
    // track count, and repeat() will not accept a calc() — CSS cannot narrow the number
    // down for a smaller screen by itself. Two is the most a tablet can carry without
    // the cards turning into slivers, and a phone gets one.
    $columns = (int) $columns;
    $columnStyle = $columns
        ? sprintf('--items-columns: %d; --items-columns-tablet: %d; --items-columns-mobile: 1', $columns, min($columns, 2))
        : null;
@endphp

<section class="items {{ $layout }}" @if ($columnStyle) style="{{ $columnStyle }}" @endif @if ($filter && $tags?->isNotEmpty()) x-data="tagFilter" @endif>
    <div class="main-width">
        <div class="items-header">
            @isset($head)
                <h2 id="{{ Str::slug($head) }}">{{ $head }}</h2>
            @endisset
            @isset($link, $linkLabel)
                <a class="items-link" href="{{ $link }}">{{ $linkLabel }}</a>
            @endisset
        </div>

        @if ($filter && $tags?->isNotEmpty())
            <ul class="items-tags" aria-label="{{ __('Filter by tag') }}">
                <li>
                    <a href="{{ url()->current() }}" @click.prevent="pick('')" :class="{ active: ! selected }">{{ __('All') }}</a>
                </li>
                @foreach ($tags as $tag)
                    <li>
                        <a href="{{ url()->current() }}?tag={{ $tag->slug }}" @click.prevent="pick('{{ $tag->slug }}')" :class="{ active: selected === '{{ $tag->slug }}' }">{{ $tag->name }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="items-scroller main-width">
        {{-- A scroll region has to be reachable without a mouse, or the cards past the
             edge cannot be brought into view. Only a horizontal row scrolls, and only
             when a card in it is not a link: linked cards are focusable themselves, so
             tabbing already scrolls the row along and a tabindex here would add a stop
             on every row that does nothing. --}}
        @php($needsFocus = $layout === 'items-horizontal' && $items->contains(fn ($item): bool => blank($item->url)))
        <ul class="items-container" @if ($needsFocus) tabindex="0" @endif role="group" aria-label="{{ $head ?? __('Overview') }}">
            @forelse ($items as $item)
                <li class="item article" @if ($filter) data-tags="{{ $item->tags?->pluck('slug')->implode(' ') }}" x-show="visible($el)" x-transition @endif>
                    {{-- Whole card is the link — photo, title, date and intro — but only when it has a detail URL --}}
                    @if ($item->url)
                        <a href="{{ $item->url }}" draggable="false">
                            @include('components.item-card-body', ['item' => $item])
                        </a>
                    @else
                        <div class="item-body">
                            @include('components.item-card-body', ['item' => $item])
                        </div>
                    @endif
                </li>
            @empty
                <li class="item items-empty">{{ __('Nothing to show yet.') }}</li>
            @endforelse
        </ul>
    </div>
</section>
