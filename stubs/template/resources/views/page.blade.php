@extends('layouts.app')

@section('content')
    {{-- One section carries the page's h1 and every other heading is an h2. Which one is
         decided here rather than by each section for itself: a section cannot see whether
         an earlier one already held a heading. values() so the index lines up with $loop,
         since sections() sorts and filters and keeps the original keys. --}}
    @php
        $sections = $page->sections()->values();
        $headingIndex = App\Http\Controllers\PageController::headingSectionIndex($sections);
    @endphp

    <main id="main">
        @foreach ($sections as $section)
            @include($section->_view ?? 'sections.' . $section->_name, ['headLevel' => $loop->index === $headingIndex ? 'h1' : 'h2'])
        @endforeach
    </main>
@endsection
