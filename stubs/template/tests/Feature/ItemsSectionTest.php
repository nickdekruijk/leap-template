<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\Page;
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
