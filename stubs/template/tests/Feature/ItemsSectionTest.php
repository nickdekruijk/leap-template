<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\Page;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ResolvesContentPaths;
use Tests\TestCase;

/**
 * How a card row is laid out. This used to be decided by the limit alone: empty meant
 * every item in a grid, a number meant a sideways-scrolling teaser, and there was no way
 * to ask for six items in three columns. Layout, column count and limit are separate
 * settings now — and none of it was covered by a test before.
 */
class ItemsSectionTest extends TestCase
{
    use RefreshDatabase;
    use ResolvesContentPaths;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(News::class)) {
            $this->markTestSkipped('Installed without the news content type.');
        }
    }

    public function test_a_limited_section_can_be_a_grid(): void
    {
        $this->seedNews(8);
        $page = $this->pageWithSection(['limit' => 6, 'layout' => 'grid']);

        $html = $this->get('/'.$page->slug)->assertOk()->getContent();

        $this->assertStringContainsString('class="items items-grid"', $html);
        $this->assertSame(6, substr_count($html, 'class="item article"'));
    }

    public function test_a_section_can_be_a_horizontal_row(): void
    {
        $this->seedNews(4);
        $page = $this->pageWithSection(['layout' => 'horizontal']);

        $html = $this->get('/'.$page->slug)->assertOk()->getContent();

        $this->assertStringContainsString('class="items items-horizontal"', $html);
    }

    /**
     * Sections saved before the field existed have no layout key. They must keep looking
     * exactly as they did, or every site that upgrades finds its rows rearranged.
     */
    public function test_a_section_without_a_layout_falls_back_to_the_old_rule(): void
    {
        $this->seedNews(4);

        $limited = $this->pageWithSection(['limit' => 2]);
        $unlimited = $this->pageWithSection([]);

        $this->assertStringContainsString(
            'class="items items-horizontal"',
            $this->get('/'.$limited->slug)->assertOk()->getContent(),
        );
        $this->assertStringContainsString(
            'class="items items-grid"',
            $this->get('/'.$unlimited->slug)->assertOk()->getContent(),
        );
    }

    public function test_a_column_count_is_written_out_for_every_screen_size(): void
    {
        $this->seedNews(4);
        $page = $this->pageWithSection(['layout' => 'grid', 'columns' => 4]);

        $html = $this->get('/'.$page->slug)->assertOk()->getContent();

        // A tablet gets at most two, a phone one — CSS cannot narrow the number down
        // itself, since repeat() takes a count and will not evaluate a calc().
        $this->assertStringContainsString('--items-columns: 4; --items-columns-tablet: 2; --items-columns-mobile: 1', $html);
    }

    public function test_no_column_count_leaves_the_section_to_the_site(): void
    {
        $this->seedNews(4);
        $page = $this->pageWithSection(['layout' => 'grid']);

        $html = $this->get('/'.$page->slug)->assertOk()->getContent();

        $this->assertStringNotContainsString('--items-columns', $html);
    }

    public function test_a_teaser_links_to_the_overview_it_previews(): void
    {
        $this->seedNews(4);
        $page = $this->pageWithSection(['limit' => 2, 'layout' => 'grid']);

        $html = $this->get('/'.$page->slug)->assertOk()->getContent();

        $this->assertStringContainsString('<a class="items-link" href="'.$this->overviewPath('News').'">'.__('View all').'</a>', $html);
    }

    public function test_a_full_overview_does_not_link_to_itself(): void
    {
        $this->seedNews(4);
        $page = $this->pageWithSection([]);

        $html = $this->get('/'.$page->slug)->assertOk()->getContent();

        $this->assertStringNotContainsString('class="items-link"', $html);
    }

    public function test_a_link_typed_by_the_editor_wins(): void
    {
        $this->seedNews(4);
        $page = $this->pageWithSection([
            'limit' => 2,
            'link' => array_fill_keys($this->locales(), '/ergens-anders'),
            'link_label' => array_fill_keys($this->locales(), 'Meer hierover'),
        ]);

        $html = $this->get('/'.$page->slug)->assertOk()->getContent();

        $this->assertStringContainsString('<a class="items-link" href="/ergens-anders">Meer hierover</a>', $html);
        $this->assertStringNotContainsString(__('View all'), $html);
    }

    /**
     * An overview page is a page with one card row on it, so that row's heading is the
     * only thing on it that can be the page's h1 — it used to be hard-coded to h2, which
     * left news and events without one.
     */
    public function test_the_heading_of_a_full_overview_is_the_pages_h1(): void
    {
        $this->seedNews(4);
        $page = $this->makePage(
            array_fill_keys($this->locales(), 'Nieuwsoverzicht'),
            [['_name' => 'news', '_view' => 'sections.items', '_sort' => 0, 'active' => true, 'head' => array_fill_keys($this->locales(), 'Al het nieuws')]],
        );

        $html = $this->get('/'.$page->slug)->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<h1 id="al-het-nieuws">Al het nieuws</h1>', $html);
    }

    /**
     * A teaser sits below a section that already holds the h1, so it steps down to h2.
     */
    public function test_a_card_row_below_a_text_section_is_an_h2(): void
    {
        $this->seedNews(4);
        $page = $this->makePage(
            array_fill_keys($this->locales(), 'Home'),
            [
                ['_name' => 'default', '_view' => 'sections.default', '_sort' => 0, 'active' => true, 'head' => array_fill_keys($this->locales(), 'Welkom')],
                ['_name' => 'news', '_view' => 'sections.items', '_sort' => 1, 'active' => true, 'limit' => 2, 'head' => array_fill_keys($this->locales(), 'Laatste nieuws')],
            ],
        );

        $html = $this->get('/'.$page->slug)->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<h1>Welkom</h1>', $html);
        $this->assertStringContainsString('<h2 id="laatste-nieuws">Laatste nieuws</h2>', $html);
    }

    /**
     * A row without a heading renders none, so the h1 moves on to the section that has one.
     */
    public function test_a_card_row_without_a_heading_hands_the_h1_on(): void
    {
        $this->seedNews(4);
        $page = $this->makePage(
            array_fill_keys($this->locales(), 'Home'),
            [
                ['_name' => 'news', '_view' => 'sections.items', '_sort' => 0, 'active' => true, 'limit' => 2],
                ['_name' => 'default', '_view' => 'sections.default', '_sort' => 1, 'active' => true, 'head' => array_fill_keys($this->locales(), 'Over ons')],
            ],
        );

        $html = $this->get('/'.$page->slug)->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<h1>Over ons</h1>', $html);
    }

    /**
     * A visitor arriving on ?tag= — from a chip on a detail page — used to get the whole
     * grid painted first and the non-matching cards blinked out once Alpine booted. The
     * server states the same answer up front now.
     */
    public function test_a_tag_in_the_url_hides_the_other_cards_before_alpine_boots(): void
    {
        [$tag, $tagged] = $this->seedTaggedNews(3);

        $html = $this->get($this->overviewPath('News').'?tag='.$tag->slug)->assertOk()->getContent();

        // One card carries the tag and stays; the other two start hidden.
        $this->assertSame(2, substr_count($html, 'style="display: none"'));
        $this->assertStringContainsString('data-tags="'.$tag->slug.'" x-show="visible($el)" x-transition ', $html);
        $this->assertStringContainsString($tagged->title, $html);
    }

    public function test_a_tag_in_the_url_marks_its_own_chip_active(): void
    {
        [$tag] = $this->seedTaggedNews(3);

        $html = $this->get($this->overviewPath('News').'?tag='.$tag->slug)->assertOk()->getContent();

        $this->assertStringContainsString($this->chip($tag->slug, active: true), $html);
        $this->assertStringContainsString($this->chip('', active: false), $html);
    }

    public function test_without_a_tag_nothing_is_hidden_and_all_is_active(): void
    {
        [$tag] = $this->seedTaggedNews(3);

        $html = $this->get($this->overviewPath('News'))->assertOk()->getContent();

        $this->assertStringNotContainsString('style="display: none"', $html);
        $this->assertStringContainsString($this->chip('', active: true), $html);
        $this->assertStringContainsString($this->chip($tag->slug, active: false), $html);
    }

    /**
     * A slug no chip offers — a renamed tag, a hand-typed URL — narrows to nothing. An
     * empty grid with no chip to explain it is worse than the unfiltered one.
     */
    public function test_an_unknown_tag_in_the_url_is_ignored(): void
    {
        [$tag] = $this->seedTaggedNews(3);

        $html = $this->get($this->overviewPath('News').'?tag=er-is-geen-tag-zo')->assertOk()->getContent();

        $this->assertStringNotContainsString('style="display: none"', $html);
        $this->assertStringContainsString($this->chip('', active: true), $html);
        $this->assertStringContainsString($this->chip($tag->slug, active: false), $html);
    }

    /**
     * The server-rendered state of one filter chip. Matched together with the click
     * handler that names the tag, since `class="active"` on its own also turns up
     * elsewhere on the page (the language switcher).
     *
     * @param  string  $slug  The tag's slug, or '' for the "All" chip.
     */
    private function chip(string $slug, bool $active): string
    {
        return 'class="'.($active ? 'active' : '').'" @click.prevent="pick(\''.$slug.'\')"';
    }

    /**
     * The overview page the teasers point at, plus enough items to be limited.
     */
    private function seedNews(int $count): void
    {
        [$default, $secondary] = $this->locales();

        $this->makePage(
            [$default => 'Nieuws', $secondary => 'News'],
            [['_name' => 'news', '_view' => 'sections.items', '_sort' => 0, 'active' => true]],
            [$default => Str::slug(__('News')), $secondary => 'news'],
        );

        News::factory()->count($count)->create();
    }

    /**
     * The overview page with $count news items, exactly one of them tagged, so a filtered
     * view has both something to keep and something to hide.
     *
     * @return array{0: Tag, 1: News}
     */
    private function seedTaggedNews(int $count): array
    {
        if (! class_exists(Tag::class) || ! method_exists(News::class, 'tags')) {
            $this->markTestSkipped('Installed without tags.');
        }

        $this->seedNews($count);

        $tag = Tag::factory()->create();
        $tagged = News::query()->first();
        $tagged->tags()->attach($tag);

        return [$tag, $tagged];
    }

    /**
     * A page carrying one news section with the given settings.
     *
     * @param  array<string, mixed>  $settings
     */
    private function pageWithSection(array $settings): Page
    {
        return $this->makePage(
            array_fill_keys($this->locales(), 'Teaser'),
            [['_name' => 'news', '_view' => 'sections.items', '_sort' => 0, 'active' => true] + $settings],
        );
    }

    /**
     * @param  array<string, string>  $title
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array<string, string>|null  $slug
     */
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
     * @return array<int, string>
     */
    private function locales(): array
    {
        return array_keys(config('leap.locales') ?: [app()->getLocale() => null]);
    }
}
