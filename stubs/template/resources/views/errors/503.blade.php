@extends('layouts.app', ['hideNavigation' => true])

@section('content')
    <main id="main">
        <div class="main-width">
            <article class="article">
                <br><br><br>
                <h1>@lang('Maintenance')</h1>
                <p>@lang('We are briefly unreachable while the site is being worked on. Please try again later.')</p>
            </article>
        </div>
    </main>
@endsection
