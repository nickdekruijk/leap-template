<?php

namespace Tests\Feature;

use App\Http\Controllers\PageController;
use App\Models\News;
use App\Models\Page;
use Database\Seeders\NewsSeeder;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\Concerns\ResolvesContentPaths;
use Tests\TestCase;

class BreadcrumbTest extends TestCase
{
    use RefreshDatabase;
    use ResolvesContentPaths;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PageSeeder::class);
    }

    public function test_the_homepage_shows_no_breadcrumb(): void
    {
        // Its trail is a single step: itself. There is nothing to show a visitor.
        $this->assertSame('', $this->trail('/'));
    }

    public function test_a_subpage_shows_home_its_parent_and_itself(): void
    {
        [$page, $parent] = $this->nestedPage();

        $trail = $this->trail($this->pathTo($page));

        $this->assertStringContainsString('href="/'.$parent->slug.'"', $trail);
        $this->assertStringContainsString($parent->title, $trail);
        // The page you are on is a position, not a destination: named, never linked.
        $this->assertStringContainsString('<span aria-current="page">'.$page->title.'</span>', $trail);
        $this->assertStringNotContainsString('href="'.$this->pathTo($page).'"', $trail);
    }

    public function test_the_home_crumb_is_a_house_icon_with_a_readable_name(): void
    {
        [$page] = $this->nestedPage();

        $trail = $this->trail($this->pathTo($page));
        $home = Page::findOrFail(1);

        $this->assertStringContainsString('href="/" title="'.$home->title.'"', $trail);
        $this->assertStringContainsString('<span class="visually-hidden">'.$home->title.'</span>', $trail);
        $this->assertStringContainsString('<svg', $trail);
    }

    public function test_the_back_link_points_at_the_crumb_before_the_current_one(): void
    {
        [$page, $parent] = $this->nestedPage();

        $trail = $this->trail($this->pathTo($page));

        $this->assertMatchesRegularExpression(
            '/<a class="breadcrumbs-back" href="\/'.preg_quote($parent->slug, '/').'"/',
            $trail
        );
    }

    public function test_an_item_hangs_under_its_overview_page(): void
    {
        $news = $this->seedNews();
        $overview = PageController::overviewPage('news');

        $trail = $this->trail($this->itemPath('News', $news->slug));

        $this->assertStringContainsString('href="/"', $trail);
        $this->assertStringContainsString('href="/'.$overview->slug.'"', $trail);
        $this->assertStringContainsString('<span aria-current="page">'.$news->title.'</span>', $trail);
    }

    public function test_a_page_can_switch_its_breadcrumb_off(): void
    {
        [$page] = $this->nestedPage();
        $page->breadcrumb = false;
        $page->save();

        $this->assertSame('', $this->trail($this->pathTo($page)));
    }

    /**
     * An item has no switch of its own — it borrows the one on the overview page it
     * hangs under, which is where its trail comes from in the first place.
     */
    public function test_an_item_follows_its_overview_pages_switch(): void
    {
        $news = $this->seedNews();
        $overview = PageController::overviewPage('news');
        $overview->breadcrumb = false;
        $overview->save();

        $this->assertSame('', $this->trail($this->itemPath('News', $news->slug)));
    }

    /**
     * The structured data is built from the same trail as the markup, so the two can
     * never disagree about where the page sits.
     */
    public function test_the_trail_is_published_as_a_breadcrumb_list(): void
    {
        [$page, $parent] = $this->nestedPage();
        $home = Page::findOrFail(1);

        $html = $this->get($this->pathTo($page))->assertOk()->getContent();

        $schema = collect($this->jsonLd($html))->firstWhere('@type', 'BreadcrumbList');
        $this->assertNotNull($schema, 'Expected a BreadcrumbList among the JSON-LD blocks');

        $this->assertSame([1, 2, 3], array_column($schema['itemListElement'], 'position'));
        $this->assertSame(
            [$home->title, $parent->title, $page->title],
            array_column($schema['itemListElement'], 'name')
        );
        $this->assertSame(url($this->pathTo($page)), $schema['itemListElement'][2]['item']);

        // Every step but the last has a URL of its own, and the last one names the page
        // being looked at: a ListItem without an item is not a step a crawler can follow.
        foreach ($schema['itemListElement'] as $step) {
            $this->assertArrayHasKey('item', $step);
        }
    }

    /**
     * An ancestor with no slug in this locale — a page that only groups its children —
     * is worth naming and not worth linking. It has to leave the structured data
     * altogether though, since a ListItem without an item is one a crawler cannot
     * follow, and the steps behind it close up.
     *
     * Rendered rather than requested: the router refuses the whole branch under a
     * slugless page, while the trail is still built wherever such a page is shown.
     */
    public function test_an_ancestor_without_a_slug_is_named_but_left_out_of_the_structured_data(): void
    {
        [$page, $parent] = $this->nestedPage();
        $home = Page::findOrFail(1);

        // Without the events: HasSlug would fill the slug back in on save.
        Page::withoutEvents(function () use ($parent): void {
            $parent->setTranslation('slug', app()->getLocale(), '');
            $parent->save();
        });

        $html = Blade::render('<x-breadcrumbs :page="$page" />', ['page' => $page->fresh()]);

        $this->assertStringContainsString('<span>'.$parent->title.'</span>', $html);
        $this->assertStringNotContainsString('>'.$parent->title.'</a>', $html);

        $schema = collect($this->jsonLd($html))->firstWhere('@type', 'BreadcrumbList');
        $this->assertSame([$home->title, $page->title], array_column($schema['itemListElement'], 'name'));
        $this->assertSame([1, 2], array_column($schema['itemListElement'], 'position'));
    }

    /**
     * A title is editor input and lands in a <script> block. Holding </script> it would
     * close that block early and put the rest of the page's own markup inside it.
     */
    public function test_a_title_cannot_break_out_of_the_structured_data(): void
    {
        [$page] = $this->nestedPage();

        // Without the events, so the slug this page is requested at stays what it was.
        Page::withoutEvents(function () use ($page): void {
            $page->title = 'Bre</script><script>alert(1)</script>ak';
            $page->save();
        });

        $html = $this->get($this->pathTo($page))->assertOk()->getContent();

        $this->assertStringContainsString('</script>', $html);

        $schema = collect($this->jsonLd($html))->firstWhere('@type', 'BreadcrumbList');
        $this->assertNotNull($schema, 'The block has to stay parsable with a title like that in it');
        $this->assertSame($page->fresh()->title, end($schema['itemListElement'])['name']);
    }

    /**
     * The seeded page that sits two levels deep, with its parent.
     *
     * @return array{0: Page, 1: Page}
     */
    private function nestedPage(): array
    {
        $page = Page::active()->get()->first(fn (Page $page): bool => (bool) $page->parent);
        $this->assertNotNull($page, 'Expected at least one nested page from the seeder');

        return [$page, Page::findOrFail($page->parent)];
    }

    /**
     * A nested page's path, in whichever language the site is installed in.
     */
    private function pathTo(Page $page): string
    {
        return '/'.Page::findOrFail($page->parent)->slug.'/'.$page->slug;
    }

    /**
     * The breadcrumb markup of a page, or an empty string when it has none. Scoped to
     * the trail on purpose: the navigation carries aria-current and links to the same
     * pages, so a whole-page assertion would pass on the menu alone.
     */
    private function trail(string $path): string
    {
        $html = $this->get($path)->assertOk()->getContent();

        return preg_match('/<nav class="breadcrumbs".*?<\/nav>/s', $html, $matches) ? $matches[0] : '';
    }

    /**
     * Every JSON-LD block in a page, decoded.
     *
     * @return array<int, array<string, mixed>>
     */
    private function jsonLd(string $html): array
    {
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

        return array_map(fn (string $json): array => json_decode($json, true), $matches[1]);
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
}
