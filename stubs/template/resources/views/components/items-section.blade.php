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
    'head' => null,
    'link' => null,
    'linkLabel' => null,
    'tags' => null,
    'filter' => false,
])

<section class="items {{ $layout }}" @if ($filter && $tags?->isNotEmpty()) x-data="tagFilter" @endif>
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
            <ul class="items-tags" aria-label="{{ __('Filter op label') }}">
                <li>
                    <a href="{{ url()->current() }}" @click.prevent="pick('')" :class="{ active: ! selected }">{{ __('Alles') }}</a>
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
        {{-- Only a horizontal scroller is a scroll region, so only it is keyboard-focusable --}}
        <ul class="items-container" @if ($layout === 'items-horizontal') tabindex="0" @endif role="group" aria-label="{{ $head ?? __('Overzicht') }}">
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
                <li class="item items-empty">{{ __('Nog niets om te tonen.') }}</li>
            @endforelse
        </ul>
    </div>
</section>
