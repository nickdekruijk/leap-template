<div class="search-inner">
    <div class="search-input-row">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        {{-- With a results page, Enter goes there. The page counts the search for
             Matomo itself, so the term still waiting to be reported here is dropped. --}}
        @if ($searchPage)
            <form class="search-form" method="get" action="{{ $searchPage }}" role="search" x-on:submit="$dispatch('site-search', { term: '', results: 0 })">
        @endif
        <input
            id="search-input"
            type="search"
            @if ($searchPage) name="term" @endif
            placeholder="{{ __('Search…') }}"
            wire:model.live.debounce.300ms="query"
            @keydown.slash.stop
            autocomplete="off"
            spellcheck="false">
        @if ($searchPage)
            </form>
        @endif
        <button class="search-close" x-on:click="searchOpen = false" aria-label="{{ __('Close search') }}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
        </button>
    </div>

    @if (strlen(trim($query)) >= 2)
        @if ($this->results->count())
            <ul class="search-results">
                @foreach ($this->results as $result)
                    <li wire:key="{{ $loop->index }}">
                        <a href="{{ $result['url'] }}" x-on:click="searchOpen = false">
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

            @if ($searchPage && $this->found->count() > $this->results->count())
                <p class="search-all">
                    <a href="{{ $allResultsUrl }}" x-on:click="$dispatch('site-search', { term: '', results: 0 })">{{ __('Show all :count results', ['count' => $this->found->count()]) }}</a>
                </p>
            @endif
        @else
            <p class="search-no-results">{{ __('No results found.') }}</p>
        @endif
    @endif
</div>
