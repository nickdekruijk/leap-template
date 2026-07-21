<?php

namespace Tests\Feature;

use App\Leap\Page as PageResource;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NickDeKruijk\Leap\Classes\Attribute;
use Tests\TestCase;

/**
 * What the section views make of the JSON the editor writes.
 *
 * The carousel is built from a run of slide sections: the first opens <section class="slider">
 * and the last closes it. Switching a slide off in the editor used to leave that tag behind,
 * so the section below it rendered inside the carousel — which is overflow:hidden and a fixed
 * height, and so drew on top of it.
 */
class SectionRenderTest extends TestCase
{
    use RefreshDatabase;

    private function page(array $sections): Page
    {
        return Page::forceCreate([
            'active' => true,
            'title' => 'Slides',
            'slug' => 'slides',
            'sort' => 1,
            'sections' => $sections,
        ]);
    }

    private function slide(int $sort, bool $active = true): array
    {
        return ['_name' => 'slide', '_sort' => $sort, 'active' => $active, 'head' => 'Slide '.$sort];
    }

    private function text(int $sort): array
    {
        return ['_name' => 'default', '_sort' => $sort, 'active' => true, 'head' => 'After the carousel'];
    }

    public function test_the_carousel_closes_when_its_last_slide_is_inactive(): void
    {
        $this->page([$this->slide(1), $this->slide(2, active: false), $this->text(3)]);

        $html = $this->get('/slides')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<section class="slider"'), 'The carousel opens once.');
        $this->assertStringContainsString('Slide 1', $html);
        $this->assertStringNotContainsString('Slide 2', $html, 'The inactive slide is not rendered.');
        $this->assertStringContainsString('slider-dots', $html, 'The closing markup of the run is rendered.');

        // The section below has to sit after the carousel, not inside it.
        $this->assertGreaterThan(
            strpos($html, 'slider-dots'),
            strpos($html, 'After the carousel'),
        );
    }

    public function test_the_carousel_opens_when_its_first_slide_is_inactive(): void
    {
        $this->page([$this->slide(1, active: false), $this->slide(2), $this->text(3)]);

        $html = $this->get('/slides')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<section class="slider"'));
        $this->assertStringContainsString('Slide 2', $html);
    }

    /**
     * The switch stores false rather than dropping the key, so @isset() saw every slide as
     * white — the option did nothing in either position.
     */
    public function test_white_text_follows_the_switch(): void
    {
        $this->page([
            ['_name' => 'slide', '_sort' => 1, 'active' => true, 'head' => 'Dark', 'white_text' => false],
            ['_name' => 'slide', '_sort' => 2, 'active' => true, 'head' => 'Light', 'white_text' => true],
            ['_name' => 'slide', '_sort' => 3, 'active' => true, 'head' => 'Untouched'],
        ]);

        $html = $this->get('/slides')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'slide-content article white-text"'));

        // A slide with no image draws a dark gradient and is styled white for contrast, so
        // switching the option off only shows if the view says so — an absent class loses.
        $this->assertSame(1, substr_count($html, 'slide-content article dark-text"'));
        $this->assertSame(1, substr_count($html, 'slide-content article"'), 'A section saved before the option existed keeps the default.');
    }

    /**
     * And the switch itself starts on, so the answer never depends on whether a slide was
     * saved before the option existed: `Attribute::default()` is only written when it is
     * truthy, and a slide is nearly always dark enough to want white text.
     */
    public function test_a_new_slide_starts_with_white_text_on(): void
    {
        $slide = collect((new PageResource)->attributes())
            ->first(fn (Attribute $attribute): bool => $attribute->type === 'sections')
            ->sections;

        $whiteText = collect(collect($slide)->firstWhere('name', 'slide')->attributes)
            ->firstWhere('name', 'white_text');

        $this->assertTrue($whiteText->default);
    }

    /**
     * A text section carried the same option under the name dark_background, which said what
     * it did rather than what it was picked for — and was wrong the moment the section had a
     * background photo, where nothing gains a dark background and only a wash is laid over it.
     * Pages saved under the old name are still out there.
     */
    public function test_a_text_section_reads_the_old_dark_background_key(): void
    {
        $this->page([
            ['_name' => 'default', '_sort' => 1, 'active' => true, 'head' => 'Old', 'dark_background' => true],
            ['_name' => 'default', '_sort' => 2, 'active' => true, 'head' => 'New', 'white_text' => true],
            ['_name' => 'default', '_sort' => 3, 'active' => true, 'head' => 'Off', 'white_text' => false],
        ]);

        $html = $this->get('/slides')->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'white-text"'));
    }

    public function test_a_single_slide_still_renders_a_whole_carousel(): void
    {
        $this->page([$this->slide(1), $this->text(2)]);

        $html = $this->get('/slides')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<section class="slider"'));
        $this->assertSame(1, substr_count($html, 'slider-dots'));
    }
}
