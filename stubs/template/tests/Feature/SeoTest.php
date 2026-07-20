<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\Page;
use Database\Seeders\NewsSeeder;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ResolvesContentPaths;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;
    use ResolvesContentPaths;

    public function test_document_title_appends_the_site_name_to_a_plain_page_title(): void
    {
        config(['app.name' => 'Acme']);
        $page = new Page;
        $page->title = 'Over ons';

        $this->assertSame('Over ons — Acme', $page->documentTitle());
    }

    public function test_document_title_uses_a_custom_html_title_verbatim(): void
    {
        config(['app.name' => 'Acme']);
        $page = new Page;
        $page->title = 'Over ons';
        $page->html_title = 'Custom SEO title';

        // A custom html_title stands on its own — the site name is not appended.
        $this->assertSame('Custom SEO title', $page->documentTitle());
    }

    public function test_document_title_is_the_site_name_when_there_is_no_page_title(): void
    {
        config(['app.name' => 'Acme']);

        $this->assertSame('Acme', (new Page)->documentTitle());
    }

    public function test_document_title_does_not_borrow_another_locales_html_title(): void
    {
        config(['app.name' => 'Acme', 'app.fallback_locale' => 'en']);
        app()->setLocale('nl');

        $page = new Page;
        $page->setTranslations('title', ['nl' => 'Home', 'en' => 'Home']);
        $page->setTranslations('html_title', ['en' => 'EN only title']); // nl is empty

        // The empty nl html_title must fall through to the page title, not the en one.
        $this->assertSame('Home — Acme', $page->documentTitle());
    }

    /**
     * An item's description and intro are both optional, so an item with only an intro
     * used to render no meta/og/twitter description at all — while its JSON-LD used the
     * intro and ignored the description. Both now read the same metaDescription().
     */
    public function test_an_items_meta_description_falls_back_to_its_intro(): void
    {
        $news = $this->seedNews();
        $news->setTranslations('description', array_fill_keys($this->locales(), ''));
        $news->setTranslations('intro', array_fill_keys($this->locales(), 'De korte kaarttekst'));
        $news->save();

        $html = $this->get($this->itemPath('News', $news->slug))->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="description" content="De korte kaarttekst">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="De korte kaarttekst">', $html);
        $this->assertStringContainsString('<meta name="twitter:description" content="De korte kaarttekst">', $html);
        $this->assertStringContainsString('"description":"De korte kaarttekst"', $html);
    }

    public function test_an_items_own_description_wins_over_its_intro(): void
    {
        $news = $this->seedNews();
        $news->setTranslations('description', array_fill_keys($this->locales(), 'De bewuste SEO-tekst'));
        $news->setTranslations('intro', array_fill_keys($this->locales(), 'De korte kaarttekst'));
        $news->save();

        $html = $this->get($this->itemPath('News', $news->slug))->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="description" content="De bewuste SEO-tekst">', $html);
        // The JSON-LD used to disagree with the meta tags by preferring the intro.
        $this->assertStringContainsString('"description":"De bewuste SEO-tekst"', $html);

        // The intro still shows as the item's lead paragraph — it just no longer
        // describes the page to a crawler.
        $this->assertStringContainsString('<p class="item-intro">De korte kaarttekst</p>', $html);
        $this->assertStringNotContainsString('content="De korte kaarttekst"', $html);
        $this->assertStringNotContainsString('"description":"De korte kaarttekst"', $html);
    }

    /**
     * A page has no intro at all. Reaching for one must not throw — getTranslation()
     * rejects an attribute outside $translatable, which would take down every page.
     */
    public function test_a_page_without_an_intro_still_renders_its_meta_description(): void
    {
        $this->seed(PageSeeder::class);

        $home = Page::findOrFail(1);
        $home->setTranslations('description', array_fill_keys($this->locales(), 'Beschrijving van de homepage'));
        $home->save();

        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="description" content="Beschrijving van de homepage">', false);
    }

    /**
     * A seeded news item, under the overview page its detail URL hangs from.
     */
    private function seedNews(): News
    {
        if (! class_exists(News::class)) {
            $this->markTestSkipped('Installed without the news content type.');
        }

        $this->seed(NewsSeeder::class);

        return News::query()->firstOrFail();
    }

    /**
     * @return array<int, string>
     */
    private function locales(): array
    {
        return array_keys(config('leap.locales') ?: [app()->getLocale() => null]);
    }
}
