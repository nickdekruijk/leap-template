<?php

namespace Tests\Feature;

use App\Livewire\Search;
use App\Models\News;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! config('leap.locales')) {
            $this->markTestSkipped('leap.locales is not configured; the site is monolingual.');
        }
    }

    /**
     * The two configured locales, default first.
     *
     * @return array{0: string, 1: string}
     */
    private function locales(): array
    {
        $locales = array_keys(config('leap.locales'));
        if (count($locales) < 2) {
            $this->markTestSkipped('Search locale scoping needs at least two locales.');
        }

        return [$locales[0], $locales[1]];
    }

    private function makePage(array $title, array $sections = [], ?array $slug = null): Page
    {
        [$default, $secondary] = $this->locales();
        $page = new Page;
        $page->setTranslations('title', $title);
        $page->setTranslations('slug', $slug ?? [$default => 'p'.uniqid(), $secondary => 'p'.uniqid()]);
        $page->setTranslations('description', [$default => '', $secondary => '']);
        $page->active = true;
        $page->sections = $sections;
        $page->sort = 1;
        $page->save();

        return $page;
    }

    /**
     * A news item with the given intro, under the overview page its URL hangs from —
     * Search drops an item whose URL cannot be resolved, so the overview is not
     * optional scenery. Title is fixed and description empty, so a match can only have
     * come from the intro.
     *
     * @param  array<string, string>  $intro
     */
    private function makeNews(array $intro): News
    {
        if (! class_exists(News::class)) {
            $this->markTestSkipped('Installed without the news content type.');
        }

        [$default, $secondary] = $this->locales();

        $this->makePage(
            [$default => 'Nieuws', $secondary => 'News'],
            [['_name' => 'news', '_view' => 'sections.items', '_sort' => 0, 'active' => true]],
            [$default => 'nieuws', $secondary => 'news'],
        );

        return News::factory()->create([
            'title' => [$default => 'Persbericht', $secondary => 'Press release'],
            'description' => [$default => '', $secondary => ''],
            'intro' => $intro,
            'sections' => [],
        ]);
    }

    /**
     * @return array<int, string> matched page titles in the active locale
     */
    private function search(string $term, string $locale): array
    {
        app()->setLocale($locale);

        return Livewire::test(Search::class)
            ->set('query', $term)
            ->instance()
            ->results()
            ->pluck('title')
            ->all();
    }

    public function test_title_is_matched_in_the_active_locale_only(): void
    {
        [$default, $secondary] = $this->locales();
        $this->makePage([$default => 'Over ons', $secondary => 'About us']);

        $this->assertContains('Over ons', $this->search('over', $default));
        // The secondary-locale title must not leak into the default locale
        $this->assertNotContains('Over ons', $this->search('about', $default));
        $this->assertContains('About us', $this->search('about', $secondary));
    }

    public function test_section_content_is_matched_in_the_active_locale_only(): void
    {
        [$default, $secondary] = $this->locales();
        $this->makePage(
            [$default => 'Diensten', $secondary => 'Services'],
            [['_name' => 'default', 'body' => [$default => '<p>Onze missie</p>', $secondary => '<p>Our mission</p>']]],
        );

        $this->assertContains('Diensten', $this->search('missie', $default));
        // The secondary-locale section body must not leak into the default locale
        $this->assertNotContains('Diensten', $this->search('mission', $default));
        $this->assertContains('Services', $this->search('mission', $secondary));
    }

    /**
     * An item's intro is its card text, and often the only prose it has. It is marked
     * ->searchable() in the admin Resource; the site search used to ignore it, leaving
     * such an item findable in the admin but not on its own website.
     */
    public function test_an_items_intro_is_searchable(): void
    {
        [$default] = $this->locales();
        $this->makeNews([$default => 'Onze zomeractie begint', 'en' => 'Our summer campaign starts']);

        $this->assertContains('Persbericht', $this->search('zomeractie', $default));
    }

    public function test_an_items_intro_is_matched_in_the_active_locale_only(): void
    {
        [$default, $secondary] = $this->locales();
        $this->makeNews([$default => 'Onze zomeractie begint', $secondary => 'Our summer campaign starts']);

        $this->assertContains('Persbericht', $this->search('zomeractie', $default));
        // The secondary-locale intro must not leak into the default locale
        $this->assertNotContains('Persbericht', $this->search('campaign', $default));
        $this->assertContains('Press release', $this->search('campaign', $secondary));
    }

    /**
     * Pages and items go through the same matches(), but only an item has an intro
     * column — an ungated intro clause fails the page query with "Unknown column".
     */
    public function test_searching_pages_survives_a_model_without_an_intro(): void
    {
        [$default, $secondary] = $this->locales();
        $this->makePage([$default => 'Contactpagina', $secondary => 'Contact page']);

        $this->assertContains('Contactpagina', $this->search('contactpagina', $default));
    }

    public function test_legacy_plain_string_rows_do_not_crash_the_query(): void
    {
        [$default] = $this->locales();

        // A pre-multilingual row: plain-string columns, not translations JSON.
        DB::table('pages')->insert([
            'title' => 'Legacy Titel',
            'description' => 'oude beschrijving',
            'slug' => 'legacy-page',
            'active' => true,
            'sections' => json_encode([['_name' => 'default', 'body' => '<p>Legacy sectie tekst</p>']]),
            'sort' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Must not throw (json_valid guard), and legacy section text stays
        // searchable. The plain-string title reads back empty via Spatie (until the
        // row is migrated), so assert the row is found rather than its title text.
        $this->assertCount(1, $this->search('legacy sectie', $default));
    }
}
