{{-- The inside of a content card: thumbnail, title, optional date/time, intro.
     Shared by the linked and unlinked card in items-section.blade.php. --}}
<div class="item-thumbnail">
    @php($image = $item->media->first())
    @if ($image)
        {{-- draggable=false: a native image drag would hijack the horizontal scroller's own drag --}}
        <x-responsive-image :media="$image" sizes="(max-width: 550px) 80vw, 360px" :widths="[600, 900, 1200]" fallback="600" draggable="false" />
    @else
        <span class="image-placeholder" aria-hidden="true"></span>
    @endif
</div>

<h3>{{ $item->title }}</h3>

@if (! empty($item->date))
    <p class="item-date">
        {{-- Short weekday on a card, where the line has to stay narrow; the detail page
             writes it out. Dutch keeps day names lowercase and abbreviates them with a
             full stop ("do."), but here the date opens the line and reads as a label,
             so it is capitalised and the stop goes. --}}
        <time datetime="{{ $item->date->toDateString() }}">{{ Str::ucfirst(rtrim($item->date->translatedFormat('D'), '.')) }} {{ $item->date->translatedFormat('j F Y') }}</time>
        @if (! empty($item->start_time))
            <span class="item-time">{{ Str::substr($item->start_time, 0, 5) }}@if (! empty($item->end_time))–{{ Str::substr($item->end_time, 0, 5) }}@endif</span>
        @endif
    </p>
@endif

@isset($item->intro)
    <p>{{ $item->intro }}</p>
@endisset
