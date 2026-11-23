<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use NickDeKruijk\LeapTemplate\Tests\TestCase;

/**
 * What Matomo hears about that loads no page. The tag filter changes the address with
 * pushState and the live search changes nothing in it, so both tell the page through a
 * browser event, and the Matomo snippet in the layout listens. The names have to agree
 * across three files, in two languages, and a typo in one is silent: Matomo simply never
 * hears of it. GA4 sees the history changes by itself, so nothing here talks to it.
 */
class AnalyticsEventsTest extends TestCase
{
    private function stub(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/stubs/template/'.$path);
    }

    /**
     * The Matomo script in the layout, which only renders when Matomo is configured.
     */
    private function matomoSnippet(): string
    {
        $layout = $this->stub('resources/views/layouts/app.blade.php');

        $this->assertSame(1, preg_match("/@if \\(config\\('leap\\.consent\\.matomo\\.url'\\).*?@endif/s", $layout, $matches));

        return $matches[0];
    }

    public function test_the_tag_filter_reports_a_page_view_that_matomo_counts(): void
    {
        $this->assertStringContainsString("new CustomEvent('page:view'", $this->stub('resources/js/scripts.js'));

        $snippet = $this->matomoSnippet();

        $this->assertStringContainsString("addEventListener('page:view'", $snippet);
        $this->assertStringContainsString("'setCustomUrl', e.detail.url", $snippet);
        $this->assertStringContainsString("'trackPageView'", $snippet);
    }

    public function test_the_search_reports_its_term_and_results_as_a_site_search(): void
    {
        $this->assertStringContainsString("dispatch('site-search', term:", $this->stub('app/Livewire/Search.php'));

        $snippet = $this->matomoSnippet();

        $this->assertStringContainsString("addEventListener('site-search'", $snippet);
        $this->assertStringContainsString("'trackSiteSearch', pending.term, false, pending.results", $snippet);
    }
}
