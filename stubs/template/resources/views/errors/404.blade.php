@extends('layouts.app')

@section('content')
    {{-- id="main", so the skip link at the top of the layout lands here as it does on
         every other page. It used to be an anchor with a name attribute, which browsers
         stopped honouring as a link target a very long time ago — leaving the one link a
         keyboard user has on this page pointing at nothing. --}}
    <main id="main">
        <div class="main-width">
            <article class="article">
                <br><br><br>
                <h1>@lang('4 oh 4')</h1>
                <p>@lang('Sorry, this page does not exist (any more).')</p>
                <a href="{{ \NickDeKruijk\Leap\Leap::localePrefix() ?: '/' }}" role="button">@lang('Back to the homepage')</a>
            </article>
        </div>
    </main>
@endsection
