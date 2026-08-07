<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use NickDeKruijk\LeapTemplate\Tests\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * No Alpine expression in the stubs names a global.
 *
 * A site that wants to keep 'unsafe-eval' out of its Content-Security-Policy has to load
 * Alpine's CSP build, which parses the expressions in the markup itself rather than
 * handing them to the Function constructor. Its grammar is wide — object literals,
 * comparisons, ternaries, assignment, calls with arguments all work — but it refuses any
 * value that sits on globalThis, which is to say `window`, `document`, `console`, `Math`
 * and the rest.
 *
 * The template is not CSP-ready on its own: it renders a Livewire component and takes
 * Alpine from Livewire's bundle, which is the standard build (livewire.csp_safe would
 * change that, at about 120KB). But its own markup has no reason to stand in the way, and
 * the failure it would cause is the quiet kind — the expression simply never evaluates,
 * and a menu that does not respond looks like a menu nobody clicked.
 *
 * There was one: x-on:scroll.window="scrolling = window.pageYOffset > 0" on <body>. It
 * lives in navigation() now, where it is ordinary JavaScript again.
 */
class AlpineExpressionsTest extends TestCase
{
    /**
     * Values on globalThis. Anything reached through one of these is refused, so an
     * expression naming any of them cannot run under the CSP build.
     */
    private const GLOBALS = ['window', 'document', 'console', 'Math', 'JSON', 'localStorage', 'sessionStorage', 'navigator', 'location'];

    public function test_no_stub_puts_a_global_in_an_alpine_expression(): void
    {
        $offenders = [];

        $files = Finder::create()
            ->files()
            ->in(__DIR__.'/../../stubs')
            ->name('*.blade.php');

        foreach ($files as $file) {
            $html = (string) file_get_contents($file->getPathname());

            // Alpine's directives, in both spellings, plus the : and @ shorthands.
            $pattern = '/\b(?:x-(?:data|show|if|text|html|model|init|effect|bind:[\w:.-]+|on:[\w:.-]+)|@[\w:.-]+|:[\w-]+)="([^"]*)"/';

            if (! preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as [$whole, $expression]) {
                foreach (self::GLOBALS as $global) {
                    if (preg_match('/\b'.$global.'\s*[.\[(]/', $expression)) {
                        $offenders[] = $file->getRelativePathname().': '.trim($whole);
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These expressions name a global, which Alpine's CSP build refuses. Move the "
            ."behaviour into the Alpine.data component, where it is plain JavaScript:\n"
            .implode("\n", $offenders)
        );
    }
}
