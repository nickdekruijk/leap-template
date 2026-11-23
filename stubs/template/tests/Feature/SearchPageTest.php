<?php

namespace Tests\Feature;

use App\Livewire\Search;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use Tests\Concerns\ReadsTheSiteLanguage;
use Tests\TestCase;

/**
 * The results page behind the search popup: off unless config('leap.search.page') gives
 * it a slug, and then every match, a page at a time, at an address of its own.
 */
class SearchPageTest extends TestCase
{
    use ReadsTheSiteLanguage;
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    private function locales(): array
    {
        return array_keys(config('leap.locales') ?: [app()->getLocale() => null]);
    }

    /**
     * Pages called "Lantern 1" to "Lantern $count", so a search for "lantern" finds
     * exactly them.
     */
    private function makeLanterns(int $count): void
    {
        foreach (range(1, $count) as $number) {
            $page = new Page;
            $page->setTranslations('title', array_fill_keys($this->locales(), 'Lantern '.$number));
            $page->setTranslations('slug', array_fill_keys($this->locales(), 'lantern-'.$number));
            $page->setTranslations('description', array_fill_keys($this->locales(), ''));
            $page->active = true;
            $page->sections = [];
            $page->sort = $number;
            $page->save();
        }
    }

    /**
     * The address of the results page in the default language.
     */
    private function searchPath(string $slug = 'search'): string
    {
        return Leap::localePrefix(Leap::localeDefault()).'/'.$slug;
    }

    public function test_the_site_has_no_results_page_unless_it_asks_for_one(): void
    {
        config(['leap.search.page' => false]);

        $this->get($this->searchPath().'?term=lantern')->assertNotFound();
    }

    public function test_the_popup_neither_links_to_nor_submits_to_a_page_that_is_not_there(): void
    {
        config(['leap.search.page' => false]);
        $this->makeLanterns(35);

        Livewire::test(Search::class)
            ->set('query', 'lantern')
            ->assertDontSeeHtml('<form class="search-form"')
            ->assertDontSee('Show all');

        // Without a page to go to, the popup is the whole list there is
        $this->assertCount(30, Livewire::test(Search::class)->set('query', 'lantern')->instance()->results());
    }

    public function test_the_results_page_shows_every_match_a_page_at_a_time(): void
    {
        config(['leap.search.page' => 'search']);
        $this->makeLanterns(45);

        $first = $this->get($this->searchPath().'?term=lantern')->assertOk();
        $this->assertSame(20, substr_count($first->getContent(), 'class="search-result-title"'));

        // The term travels with the pages, or page 2 searches for nothing
        $first->assertSee('term=lantern&amp;page=2', false);

        $last = $this->get($this->searchPath().'?term=lantern&page=3')->assertOk();
        $this->assertSame(5, substr_count($last->getContent(), 'class="search-result-title"'));

        $this->get($this->searchPath().'?term=lantern&page=4')->assertNotFound();
    }

    public function test_the_results_page_is_followed_but_not_indexed(): void
    {
        config(['leap.search.page' => 'search']);
        $this->makeLanterns(1);

        $this->get($this->searchPath().'?term=lantern')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_the_space_around_the_term_is_ignored_and_an_empty_term_asks_for_one(): void
    {
        config(['leap.search.page' => 'search']);
        $this->makeLanterns(1);

        $this->get($this->searchPath().'?term='.urlencode('  lantern '))
            ->assertOk()
            ->assertSee('Lantern 1');

        $this->get($this->searchPath())
            ->assertOk()
            ->assertSee('name="term"', false)
            ->assertDontSee(__('No results found.'));
    }

    /**
     * With a page to go to the popup keeps to the best few, and says how many more
     * there are: the only way to them besides Enter.
     */
    public function test_the_popup_shows_the_best_few_and_links_to_the_rest(): void
    {
        config(['leap.search.page' => 'search']);
        $this->makeLanterns(12);

        $popup = Livewire::test(Search::class)->set('query', 'lantern');

        $this->assertCount(8, $popup->instance()->results());
        $popup->assertSeeHtml('<form class="search-form"')
            ->assertSee(__('Show all :count results', ['count' => 12]));

        // Everything fits: no link to a page that shows the same list
        Livewire::test(Search::class)->set('query', 'lantern 1')->assertDontSee('Show all');
    }

    public function test_each_language_has_its_own_slug(): void
    {
        if (count(Leap::localesPublished()) < 2) {
            $this->markTestSkipped('The site publishes one language.');
        }

        [$default, $secondary] = array_keys(Leap::localesPublished());

        config(['leap.search.page' => [$default => 'zoeken', $secondary => 'search']]);
        $this->makeLanterns(1);

        $this->get('/zoeken?term=lantern')->assertOk()->assertSee('Lantern 1');
        $this->get('/'.$secondary.'/search?term=lantern')->assertOk()->assertSee('Lantern 1');

        // Each slug belongs to its own language
        $this->get('/search?term=lantern')->assertNotFound();
        $this->get('/'.$secondary.'/zoeken?term=lantern')->assertNotFound();
    }
}
