@extends('layouts.app')

{{--
    The results page (PageController::search()), when config('leap.search.page') gives
    the site one. A plain GET form and a plain list, so it works without JavaScript and
    every page of results has an address of its own. The popup leads here with its
    "Show all" link and with Enter.
--}}
@section('content')
    <main id="main" class="search-page">
        <div class="main-width">
            <article class="article">
                <h1>@lang('Search')</h1>

                <form class="search-page-form" method="get" action="{{ App\Http\Controllers\PageController::searchUrl() }}" role="search">
                    <label class="visually-hidden" for="search-page-term">@lang('Search the site')</label>
                    <input id="search-page-term" type="search" name="term" value="{{ $searchTerm }}" placeholder="{{ __('Search…') }}" autocomplete="off" spellcheck="false">
                    <button type="submit">@lang('Search')</button>
                </form>

                @if (strlen(App\Support\SiteSearch::term($searchTerm)) >= 2)
                    @if ($results->total())
                        <p class="search-page-count">{{ trans_choice(':count result for “:term”|:count results for “:term”', $results->total(), ['term' => $searchTerm]) }}</p>

                        <ul class="search-results">
                            @foreach ($results as $result)
                                <li>
                                    <a href="{{ $result['url'] }}">
                                        <strong class="search-result-title">{{ $result['title'] }}</strong>
                                        @if (! empty($result['label']))
                                            <span class="search-result-label">{{ $result['label'] }}</span>
                                        @endif
                                        @if ($result['excerpt'])
                                            <span class="search-result-excerpt">{{ $result['excerpt'] }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>

                        <x-pagination :paginator="$results" />
                    @else
                        <p class="search-no-results">@lang('No results found.')</p>
                    @endif
                @endif
            </article>
        </div>
    </main>
@endsection
