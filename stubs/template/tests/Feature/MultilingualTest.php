<?php

namespace Tests\Feature;

use App\Http\Controllers\PageController;
use App\Models\Page;
use App\Models\Tag;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultilingualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! config('leap.locales')) {
            $this->markTestSkipped('leap.locales is not configured; the site is monolingual.');
        }

        $this->seed(PageSeeder::class);
    }

    /**
     * The seeds carry every language leap:template can install, and PageSeeder strips them
     * to the ones this site has. So every configured locale has a name — no more, no fewer.
     *
     * Before, a plain string landed in whichever locale happened to be active while seeding
     * and left the filter chips above every other overview reading Dutch.
     */
    public function test_the_seeded_tags_are_translated_in_every_chosen_language(): void
    {
        if (! class_exists(Tag::class)) {
            $this->markTestSkipped('Installed without tags.');
        }

        $tag = Tag::orderBy('sort')->first();
        $this->assertNotNull($tag, 'PageSeeder seeds the shared tags.');

        $locales = array_keys(config('leap.locales'));

        foreach ($locales as $locale) {
            $this->assertNotSame(
                '',
                (string) $tag->getTranslation('name', $locale, false),
                "The seeded tags have no name in {$locale}, so its filter chips read another language.",
            );
        }

        $this->assertSame(
            $locales,
            array_keys($tag->getTranslations('name')),
            'And nothing beyond them: seeding a language the site does not have is database litter.',
        );
    }

    public function test_the_default_locale_is_served_unprefixed(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_a_secondary_locale_is_served_under_its_prefix(): void
    {
        $secondary = array_keys(config('leap.locales'))[1] ?? null;
        $this->assertNotNull($secondary, 'Expected at least two configured locales');

        $this->get('/'.$secondary)->assertOk();
    }

    public function test_the_homepage_lists_hreflang_alternates(): void
    {
        $this->get('/')->assertSee('hreflang', false);
    }

    /**
     * A slug is an address, not prose, so it must never fall back to another language.
     *
     * laravel-translatable falls back to config('app.fallback_locale') whenever an attribute is
     * read plainly, which is what $page->only() does. That is harmless while the fallback is a
     * language the site does not serve — the Laravel default 'en' on a Dutch-only site — and
     * silently wrong the moment it is one of its own: an untranslated page then answers on the
     * other locale's URL, and appears in that locale's menu, while the sitemap (which asks
     * without the fallback) already leaves it out. One line in .env, and the two disagree.
     */
    public function test_a_page_without_a_slug_in_this_locale_is_not_routable_there(): void
    {
        $this->untranslateContactSlug();

        $this->get('/en/contact')->assertNotFound();
    }

    /**
     * The other half: dropping the fallback must not make the page unreachable in the locale it
     * does have a slug for. Its own test because getPages() memoizes the tree per process with
     * once(), so a second request in the same test would answer from the first one's locale.
     */
    public function test_that_page_is_still_routable_in_the_locale_it_does_have(): void
    {
        $this->untranslateContactSlug();

        $this->get('/contact')->assertOk();
    }

    /**
     * A page that genuinely has no slug in one locale, with the fallback pointed at the other —
     * the combination under which the router used to borrow it.
     */
    private function untranslateContactSlug(): void
    {
        config([
            'leap.locales' => ['nl' => 'Nederlands', 'en' => 'English'],
            'app.fallback_locale' => 'nl',
        ]);

        // withoutEvents bypasses HasSlug's saving hook, which would regenerate the slug from the
        // title — simulating a page that genuinely has no translation for this locale.
        Page::withoutEvents(function (): void {
            $page = Page::find(4); // 'contact', a root page with no children
            $page->setTranslation('slug', 'en', '');
            $page->save();
        });
    }

    /**
     * A section heading is translatable, so on a multilingual site it is a per-locale array, and
     * loadPages() reads the raw sections cast rather than HasSections. Handing that array to the
     * menu made Str::slug() return "array" — every anchor pointing at #array — and the layout
     * render it, where htmlspecialchars() refuses an array outright. The navigation is in the
     * layout, so that was a TypeError on every page of the site.
     *
     * Nothing in the shipped seed switches 'menuitem' on, which is why a default install never
     * met it: the first editor to tick "show heading in navigation" did.
     */
    public function test_a_translatable_section_heading_renders_in_the_navigation(): void
    {
        $this->promoteHeadingToMenuItem();

        // The heading becomes an in-page anchor under its page in the menu, which the layout
        // renders on every request — so the homepage is enough to prove it.
        $this->get('/')->assertOk()->assertSee('Onze werkwijze');
    }

    /**
     * And in the active locale rather than whichever translation happened to come first. Its own
     * test for the same reason as the slug pair above: getPages() memoizes per process.
     */
    public function test_that_heading_renders_in_the_locale_being_read(): void
    {
        $this->promoteHeadingToMenuItem();

        $this->get('/en')->assertOk()->assertSee('How we work');
    }

    /**
     * Tick "show heading in navigation" on a section whose heading is translatable — which every
     * heading in ContentSections is.
     */
    private function promoteHeadingToMenuItem(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        $page = Page::find(2); // 'over-ons'/'about-us', a menu item with a default section
        $sections = $page->sections;
        $sections[0]['menuitem'] = 1;
        $sections[0]['head'] = ['nl' => 'Onze werkwijze', 'en' => 'How we work'];
        $page->sections = $sections;
        $page->save();
    }

    /**
     * The monolingual counterpart in PageRoutingTest already counted the content items;
     * this one only ever counted pages, and passed anyway because a seeded item had no
     * slug and an item without one is skipped. It was measuring the bug.
     */
    public function test_the_sitemap_has_one_entry_per_page_and_item_per_locale(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());

        // One entry per locale per active page, plus one per active content item that
        // hangs under an overview page (news/events by default).
        $expected = Page::active()->count() + collect(PageController::indexModels())
            ->filter(fn (string $model, string $type): bool => (bool) PageController::overviewPage($type))
            ->sum(fn (string $model): int => $model::active()->count());

        $this->assertCount($expected * 2, $xml->url);
    }

    public function test_the_sitemap_entry_uses_the_locale_specific_slug(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString(url('/over-ons/diensten'), $body);
        $this->assertStringContainsString(url('/en/about-us/services'), $body);
    }

    public function test_the_sitemap_lists_hreflang_alternates_including_itself(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $body);
        $this->assertStringContainsString('hreflang="nl" href="'.url('/over-ons/diensten').'"', $body);
        $this->assertStringContainsString('hreflang="en" href="'.url('/en/about-us/services').'"', $body);
    }

    public function test_the_sitemap_omits_a_locale_when_the_page_has_no_slug_translation_there(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        // withoutEvents bypasses HasSlug's saving hook, which would otherwise
        // regenerate an empty slug from the title — simulating a page that
        // genuinely has no translation for this locale (e.g. added before
        // the locale was configured).
        Page::withoutEvents(function (): void {
            $page = Page::find(4); // 'contact', a root page with no children
            $page->setTranslation('slug', 'en', '');
            $page->save();
        });

        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString(url('/en/contact'), $body);
        $this->assertStringContainsString(url('/contact'), $body);
    }

    public function test_the_sitemap_omits_a_child_page_when_its_parent_has_no_slug_translation_there(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        Page::withoutEvents(function (): void {
            $parent = Page::find(2); // 'over-ons'/'about-us', parent of 'diensten'/'services'
            $parent->setTranslation('slug', 'en', '');
            $parent->save();
        });

        // Neither parent nor child should appear under /en, even though the
        // child ('diensten'/'services') still has its own en slug — proves
        // buildLocalePath() cascades non-routability from the ancestor.
        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString(url('/en/about-us'), $body);
        $this->assertStringNotContainsString(url('/en/about-us/services'), $body);
        $this->assertStringContainsString(url('/over-ons/diensten'), $body);
    }
}
