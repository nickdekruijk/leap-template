<?php

namespace Tests\Feature;

use App\Leap\Page as PageResource;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Classes\Attribute;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * A video section fetches its poster from YouTube or Vimeo once and stores it on the
     * public disk, so rendering one in a test reached across the network and then left a
     * file behind in the project's own media library — a stranger in the file manager
     * that came back every time the suite ran.
     *
     * Both halves are needed. Faking the request alone still writes the reply to disk,
     * and faking the disk alone still makes the call: a suite that fails on a train is
     * not a suite anyone trusts.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Http::fake(['*' => Http::response('poster-bytes')]);
    }

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

    /**
     * A carousel swaps its content every few seconds, so the heading a visitor happens to
     * land on is not the one the page is about. The h1 goes to the first section below it
     * that renders a heading of its own.
     */
    public function test_a_carousel_carries_no_h1(): void
    {
        $this->page([$this->slide(1), $this->slide(2), $this->text(3)]);

        $html = $this->get('/slides')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), 'The page has exactly one h1.');
        $this->assertStringContainsString('<h1>After the carousel</h1>', $html);
        $this->assertSame(2, substr_count($html, '<p class="head">'), 'Both slide headings are paragraphs.');
    }

    /**
     * One slide is a static hero rather than a carousel: nothing swaps it out, so its
     * heading is the one the page is about.
     */
    public function test_a_lone_slide_carries_the_h1(): void
    {
        $this->page([$this->slide(1), $this->text(2)]);

        $html = $this->get('/slides')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<h1 class="head">Slide 1</h1>', $html);
        $this->assertStringContainsString('<h2>After the carousel</h2>', $html);
    }

    /**
     * Which is decided on what is actually shown: switch a slide off and the one left over
     * is a hero, so it takes the h1 the pair of them could not have.
     */
    public function test_the_only_active_slide_of_a_run_carries_the_h1(): void
    {
        $this->page([$this->slide(1), $this->slide(2, active: false), $this->text(3)]);

        $html = $this->get('/slides')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<h1 class="head">Slide 1</h1>', $html);
    }

    /**
     * A quote renders a blockquote and a video's head is a screen-reader label on the play
     * button — neither is a heading, so neither can be the page's h1.
     */
    public function test_a_quote_or_a_video_hands_the_h1_on(): void
    {
        $this->page([
            ['_name' => 'quote', '_view' => 'sections.default', '_sort' => 1, 'active' => true, 'head' => 'Quoted'],
            ['_name' => 'video', '_sort' => 2, 'active' => true, 'head' => 'Watch this', 'video_id' => 'dQw4w9WgXcQ'],
            $this->text(3),
        ]);

        $html = $this->get('/slides')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<h1>After the carousel</h1>', $html);
    }

    private function video(string $id): array
    {
        return ['_name' => 'video', '_sort' => 1, 'active' => true, 'head' => 'Watch this', 'video_id' => $id];
    }

    /**
     * With consent on, a visitor who has not allowed embeds is asked before the player
     * loads. The notice names the provider, so no separate line does.
     */
    public function test_a_video_asks_first_while_consent_is_on(): void
    {
        config()->set('leap.consent.enabled', true);

        $this->page([$this->video('dQw4w9WgXcQ')]);

        $html = $this->get('/slides')->assertOk()->getContent();

        $this->assertStringContainsString('<div class="video-consent" hidden>', $html);
        $this->assertStringContainsString('video-consent-once', $html);
        $this->assertStringContainsString('video-consent-always', $html);
        $this->assertStringNotContainsString('video-provider', $html);
    }

    /**
     * With consent off nobody is asked, so "Always allow" has nothing to remember and the
     * notice came back with every video. It is not rendered at all: the script then plays
     * on the first click, and a line under the video says where it comes from.
     */
    public function test_a_video_plays_on_one_click_while_consent_is_off(): void
    {
        config()->set('leap.consent.enabled', false);

        $this->page([$this->video('dQw4w9WgXcQ')]);

        $html = $this->get('/slides')->assertOk()->getContent();

        $this->assertStringNotContainsString('video-consent', $html);
        $this->assertStringContainsString(
            '<p class="video-provider">'.__('Video from :provider', ['provider' => 'YouTube']).'</p>',
            $html,
        );
        $this->assertStringContainsString('class="video-poster"', $html);
    }

    public function test_the_line_under_a_video_names_vimeo_for_a_vimeo_id(): void
    {
        config()->set('leap.consent.enabled', false);

        $this->page([$this->video('1084537')]);

        $this->assertStringContainsString(
            '<p class="video-provider">'.__('Video from :provider', ['provider' => 'Vimeo']).'</p>',
            $this->get('/slides')->assertOk()->getContent(),
        );
    }

    /**
     * Whichever way consent is set, the page asks the provider for nothing until the
     * visitor clicks: no iframe, and the only address of theirs in the markup is the one
     * the click handler reads from data-video. The poster is the local copy.
     */
    #[DataProvider('videosAndConsent')]
    public function test_a_video_reaches_no_provider_before_the_click(bool $enabled, string $id): void
    {
        config()->set('leap.consent.enabled', $enabled);

        $this->page([$this->video($id)]);

        $html = $this->get('/slides')->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<section class="video">.*?<\/section>/s', $html, $match));
        $this->assertSame(1, substr_count($match[0], 'data-video="https://'));

        $rest = preg_replace('/data-video="[^"]*"/', '', $match[0]);

        $this->assertStringNotContainsString('<iframe', $rest);
        $this->assertDoesNotMatchRegularExpression('/youtube(-nocookie)?\.com|ytimg\.com|vimeo\.com|vimeocdn\.com/', $rest);
    }

    /**
     * @return array<string, array{bool, string}>
     */
    public static function videosAndConsent(): array
    {
        return [
            'consent on, YouTube' => [true, 'dQw4w9WgXcQ'],
            'consent on, Vimeo' => [true, '1084537'],
            'consent off, YouTube' => [false, 'dQw4w9WgXcQ'],
            'consent off, Vimeo' => [false, '1084537'],
        ];
    }

    /**
     * The cookie table is no longer offered in the editor while consent is off, but a
     * page that already has it keeps showing it: the view comes from the stored section.
     */
    public function test_a_stored_cookie_table_still_renders_while_consent_is_off(): void
    {
        config()->set('leap.consent.enabled', false);

        $this->page([['_name' => 'cookies', '_sort' => 1, 'active' => true, 'head' => 'Cookies']]);

        $html = $this->get('/slides')->assertOk()->getContent();

        $this->assertStringContainsString('<section class="default none cookies">', $html);
        $this->assertStringContainsString('class="cookie-table"', $html);
    }

    /**
     * An empty <h1></h1> is worse than no heading at all, and it would leave the section
     * that does have one below an h2 with nothing above it.
     */
    public function test_an_empty_heading_renders_no_tag_and_hands_the_h1_on(): void
    {
        $this->page([
            ['_name' => 'default', '_sort' => 1, 'active' => true, 'head' => '', 'body' => '<p>No heading here</p>'],
            $this->text(2),
        ]);

        $html = $this->get('/slides')->assertOk()->getContent();

        $this->assertStringNotContainsString('<h1></h1>', $html);
        $this->assertStringNotContainsString('<h2></h2>', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<h1>After the carousel</h1>', $html);
    }
}
