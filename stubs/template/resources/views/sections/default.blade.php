@php($bg = ($section['background'] ?? null)?->first())
{{-- dark_background is what this option was called before it was named after what an
     editor picks it for, the same as on a slide. A section saved under the old name keeps
     working; the editor writes the new one from the first save. --}}
@php($whiteText = ! empty($section['white_text'] ?? $section['dark_background'] ?? null))
<section
    class="default {{ $section['image_position'] ?? 'right' }} {{ $section->_name }}{{ $whiteText ? ' white-text' : '' }}">
    @if ($bg)
        <x-responsive-image class="section-bg" :media="$bg" sizes="100vw" :widths="[900, 1200, 1600, 1920, 2560]" fallback="1600" decorative />
    @endif
    <div class="main-width">
        <article class="article">
            @if ($section->_name === 'quote')
                <blockquote>&ldquo;{!! $section['head'] ?? '' !!}&rdquo;</blockquote>
                @isset($section['body'])
                    <p class="quote-source">&mdash; {!! $section['body'] !!}</p>
                @endisset
            @else
                {{-- The first section carries the page's h1 — except on an item, whose
                     header already has one; a second h1 there would compete with it. --}}
                @php($level = $loop->first && empty($hasHeading) ? 'h1' : 'h2')
                <{{ $level }}>{!! $section['head'] !!}</{{ $level }}>
                {!! $section['body'] ?? '' !!}
            @endif
        </article>
        @isset($section->image)
            <div class="images">
                @foreach ($section->image as $image)
                    <x-responsive-image :media="$image" sizes="(max-width: 550px) 100vw, 50vw" :widths="[600, 900, 1200, 1600]" fallback="900" />
                @endforeach
            </div>
        @elseif (isset($section['image_position']))
            <div class="images">
                <span class="image-placeholder" aria-hidden="true"></span>
            </div>
        @endisset
    </div>
</section>
