<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use NickDeKruijk\LeapTemplate\Tests\TestCase;

/**
 * What the template does on a site that switched Leap's consent off
 * (leap.consent.enabled false), because its banner comes from somewhere else.
 *
 * The video notice used to show there as well. "Always allow" then remembered nothing,
 * since with consent off nothing is stored, and the question came back with every video.
 *
 * This suite reads the stubs as files; the tests that render them live in
 * stubs/template/tests/Feature and run in a generated site.
 */
class ConsentOffStubsTest extends TestCase
{
    private function stub(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/stubs/template/'.$path);
    }

    public function test_the_video_notice_is_only_rendered_while_consent_is_on(): void
    {
        $view = $this->stub('resources/views/sections/video.blade.php');

        $if = strpos($view, '@if (NickDeKruijk\Leap\Classes\Consent::enabled())');
        $else = strpos($view, "\n@else\n", (int) $if);
        $endif = strpos($view, "\n@endif\n", (int) $else);

        $this->assertNotFalse($if);
        $this->assertNotFalse($else);
        $this->assertNotFalse($endif);

        $on = substr($view, $if, $else - $if);
        $off = substr($view, $else, $endif - $else);

        $this->assertStringContainsString('<div class="video-consent" hidden>', $on);
        $this->assertStringNotContainsString('video-provider', $on);

        $this->assertStringContainsString("<p class=\"video-provider\">{{ __('Video from :provider', ['provider' => \$video->provider()]) }}</p>", $off);
        $this->assertStringNotContainsString('<div class="video-consent"', $off);
        $this->assertStringNotContainsString('<button', $off);
    }

    /**
     * Blade keeps the whitespace in front of a directive. With these three indented, a
     * site with consent on would get a few extra spaces in every video section, and the
     * promise that nothing changes there would no longer hold to the byte.
     */
    public function test_the_directives_around_the_notice_add_nothing_to_the_page(): void
    {
        $view = $this->stub('resources/views/sections/video.blade.php');

        $this->assertStringContainsString("    </button>\n@if (NickDeKruijk\Leap\Classes\Consent::enabled())\n\n    {{--", $view);
        $this->assertStringContainsString("    </div>\n@else\n", $view);
        $this->assertStringContainsString("</p>\n@endif\n</section>\n", $view);
    }

    /**
     * Without a notice in the markup the script has to play straight away, whatever
     * has() says: with consent off and the default on "denied" it says no.
     */
    public function test_the_script_plays_at_once_when_there_is_no_notice(): void
    {
        $js = $this->stub('resources/js/scripts.js');

        $this->assertStringContainsString("const notice = section.querySelector('.video-consent');", $js);
        $this->assertStringContainsString("if (!notice || window.consent.has('embeds')) {", $js);
    }

    public function test_the_line_under_the_video_is_styled(): void
    {
        $this->assertStringContainsString('.video-provider {', $this->stub('resources/css/template.scss'));
    }

    public function test_the_cookie_table_section_is_only_offered_while_consent_is_on(): void
    {
        $sections = $this->stub('app/Leap/Concerns/ContentSections.php');

        $guard = strpos($sections, 'if (Consent::enabled()) {');
        $cookies = strpos($sections, "Section::make('cookies')");

        $this->assertStringContainsString('use NickDeKruijk\Leap\Classes\Consent;', $sections);
        $this->assertNotFalse($guard);
        $this->assertGreaterThan($guard, $cookies);

        // Every other section stays outside the condition.
        $this->assertLessThan($guard, strpos($sections, "Section::make('video')"));
    }
}
