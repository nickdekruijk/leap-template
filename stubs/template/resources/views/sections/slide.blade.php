@if ($section->_first)
    <section class="slider" aria-label="{{ __('Slider') }}" id="slider-{{ $loop->iteration }}">
@endif
        @php($image = ($section['image'] ?? null)?->first())
        {{-- A slide without an image falls back to a dark gradient, so its text defaults to
             white. Switching the option off has to beat that default, which an absent class
             cannot do — hence the explicit dark-text. Null is a section saved before the
             option existed: it keeps the default. --}}
        @php($whiteText = $section['white_text'] ?? null)
        <div class="slide @if (! $image) slide-placeholder @endif" role="group" aria-roledescription="{{ __('slide') }}" aria-label="{{ __('Slide :n', ['n' => $loop->iteration]) }}">
            @if ($image && str_ends_with($image->file_name, '.mp4'))
                <video src="{{ asset('storage/' . $image->file_name) }}" loop muted playsinline autoplay aria-hidden="true"></video>
            @elseif ($image)
                <x-responsive-image :media="$image" sizes="100vw" :widths="[900, 1200, 1600, 1920, 2560]" fallback="1600" :eager="$loop->first" decorative />
            @endif
            <div class="main-width">
                <div class="slide-content article{{ $whiteText === null ? '' : ($whiteText ? ' white-text' : ' dark-text') }}">
                    {{-- A carousel carries no h1: its content is swapped every few seconds,
                         so the heading a visitor lands on is not the one the page is about.
                         A lone slide is a static hero rather than a carousel, and the page
                         hands it the h1 then (see PageController::headingSectionIndex()).
                         Anything else is a p — a run of slides must not build a heading
                         structure of its own either. --}}
                    @isset($section->head)
                        @php($headTag = ($headLevel ?? 'h2') === 'h1' ? 'h1' : 'p')
                        <{{ $headTag }} class="head">{{ $section->head }}</{{ $headTag }}>
                    @endisset
                    {!! $section->body ?? '' !!}
                </div>
            </div>
        </div>
@if ($section->_last)
        <span class="slider-dots" role="tablist" aria-label="{{ __('Slides') }}"></span>
    </section>
@endif
