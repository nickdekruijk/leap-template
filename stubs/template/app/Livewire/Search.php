<?php

namespace App\Livewire;

use App\Http\Controllers\PageController;
use App\Support\SiteSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The search popup: results while you type. A plain Livewire class component (not a
 * single-file/Volt component) so it works on both Livewire 3 and 4. Mounted as
 * <livewire:search />. What a term finds is App\Support\SiteSearch's business.
 *
 * Each change of the term is also sent to the page as a "site-search" browser event,
 * with the number of results, which the layout's Matomo snippet reports as a site
 * search.
 *
 * @phpstan-import-type SearchResult from SiteSearch
 *
 * @property-read Collection<int, SearchResult> $found
 * @property-read Collection<int, SearchResult> $results
 */
class Search extends Component
{
    public string $query = '';

    /**
     * Enough for everything a small site has to match, and the list scrolls, so a
     * larger one only shows its best matches first rather than its first ten.
     */
    private const RESULT_LIMIT = 30;

    /**
     * With a results page to go to, the popup only has to show the best few: the link
     * under them, and Enter, lead to the rest.
     */
    private const RESULT_LIMIT_WITH_PAGE = 8;

    /**
     * Every match, for the count; the popup shows the first of them.
     *
     * @return Collection<int, SearchResult>
     */
    #[Computed]
    public function found(): Collection
    {
        return app(SiteSearch::class)->results($this->query);
    }

    /**
     * @return Collection<int, SearchResult>
     */
    #[Computed]
    public function results(): Collection
    {
        return $this->found->take(PageController::searchUrl() ? self::RESULT_LIMIT_WITH_PAGE : self::RESULT_LIMIT)->values();
    }

    /**
     * Tell the page what was searched and how many results it found. Every change is
     * sent, an emptied field too, so the listener can drop a term that was only typed
     * on the way to another one.
     */
    public function updatedQuery(): void
    {
        $this->dispatch('site-search', term: trim($this->query), results: $this->found->count());
    }

    public function render(): View
    {
        // The results page, or null when the site has none: where Enter goes, and the
        // same with the term as typed for the link under the list
        return view('livewire.search', [
            'searchPage' => PageController::searchUrl(),
            'allResultsUrl' => PageController::searchUrl(term: $this->query),
        ]);
    }
}
