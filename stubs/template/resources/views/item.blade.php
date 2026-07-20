{{--
    Detail page of a listed content item (news, event, project …). Same shape as
    page.blade.php — the item is built from the very same section blocks — with a title
    and intro on top, since those double as the card text in the overview and would
    otherwise have to be repeated in a section.

    $page is the item, $type its content key, $parent the overview page, $parentUrl its
    URL.
--}}
@extends('layouts.app')

@section('content')
    @include('partials.schema', ['item' => $page, 'type' => $type, 'parent' => $parent, 'parentUrl' => $parentUrl])

    <main id="main">
        <header class="item-header">
            <div class="main-width article">
                <p class="item-parent"><a href="{{ $parentUrl }}">{{ $parent->title }}</a></p>
                <h1>{{ $page->title }}</h1>
                @isset($page->intro)
                    <p class="item-intro">{{ $page->intro }}</p>
                @endisset

                {{-- Only filled-in parts show; a missing translation is an empty string,
                     not null, so filled() rather than isset(). --}}
                @php($hasTags = method_exists($page, 'tags') && $page->tags->isNotEmpty())
                @if (! empty($page->date) || filled($page->year ?? null) || filled($page->material ?? null) || filled($page->client ?? null))
                    <dl class="item-meta">
                        @if (! empty($page->date))
                            <dt>{{ __('Date') }}</dt>
                            <dd>
                                <time datetime="{{ $page->date->toDateString() }}">{{ Str::ucfirst($page->date->translatedFormat('l j F Y')) }}</time>
                                @if (! empty($page->start_time))
                                    , {{ Str::substr($page->start_time, 0, 5) }}@if (! empty($page->end_time))–{{ Str::substr($page->end_time, 0, 5) }}@endif
                                @endif
                            </dd>
                        @endif
                        @if (filled($page->client ?? null))
                            <dt>{{ __('Client') }}</dt>
                            <dd>{{ $page->client }}</dd>
                        @endif
                        @if (filled($page->material ?? null))
                            <dt>{{ __('Material') }}</dt>
                            <dd>{{ $page->material }}</dd>
                        @endif
                        @if (filled($page->year ?? null))
                            <dt>{{ __('Year') }}</dt>
                            <dd>{{ $page->year }}</dd>
                        @endif
                    </dl>
                @endif

                {{-- Tags stand apart from the meta list: they are chips, the same ones the
                     overview filters with, so they carry their own look rather than a
                     dt/dd row of comma-separated names. --}}
                @if ($hasTags)
                    <ul class="item-tags" aria-label="{{ __('Tags') }}">
                        @foreach ($page->tags as $tag)
                            <li>{{ $tag->name }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </header>

        @foreach ($page->sections()->where('active', true) as $section)
            @include($section->_view ?? 'sections.' . $section->_name, ['hasHeading' => true])
        @endforeach
    </main>
@endsection
