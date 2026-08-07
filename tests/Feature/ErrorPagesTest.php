<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use NickDeKruijk\LeapTemplate\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The error page stubs, on the three counts they used to get wrong. All three fail
 * quietly, which is why they lasted: nothing on the page says the skip link went nowhere,
 * that a search engine was invited in, or that a line about the visitor was written.
 */
class ErrorPagesTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function errorPages(): array
    {
        return [
            '404' => [__DIR__.'/../../stubs/template/resources/views/errors/404.blade.php'],
            '503' => [__DIR__.'/../../stubs/template/resources/views/errors/503.blade.php'],
        ];
    }

    /**
     * The layout's skip link points at #main. <a name="main"> stopped being a link target
     * a very long time ago, so the one link a keyboard user has on an error page pointed
     * at nothing at all.
     */
    #[DataProvider('errorPages')]
    public function test_the_skip_link_has_somewhere_to_land(string $path): void
    {
        $html = (string) file_get_contents($path);

        $this->assertStringContainsString('<main id="main">', $html);
        $this->assertStringNotContainsString('<a name="main">', $html);
    }

    /**
     * An error page answers on whatever URL was asked for, and the layout gives it a
     * canonical pointing at itself — so without this a scanner can have one empty page
     * indexed per URL it invents.
     */
    #[DataProvider('errorPages')]
    public function test_an_error_page_is_not_offered_for_indexing(string $path): void
    {
        $this->assertStringContainsString("'noindex' => true", (string) file_get_contents($path));
    }

    /**
     * The 404 view used to end in a Log::warning, after @endsection, recording the
     * visitor's IP address and user agent on every miss — personal data with no retention,
     * one line per guess a scanner made, shipped to every generated site.
     *
     * Logging a missing page is leap's job now (leap.not_found_log): off by default, no IP
     * or user agent unless a project asks, and throttled per path. It lives there because
     * this package is a dev dependency and its service provider is absent on the server,
     * which is the only place a 404 log is worth anything.
     */
    #[DataProvider('errorPages')]
    public function test_an_error_page_writes_nothing_to_the_log(string $path): void
    {
        $html = (string) file_get_contents($path);

        $this->assertStringNotContainsString('Log::', $html);
        $this->assertStringNotContainsString('request()->ip()', $html);
        $this->assertStringNotContainsString('user-agent', $html);
    }

    /**
     * The copy was hardcoded Dutch in a template that ships seven languages, while the
     * button beside it went through @lang all along.
     */
    #[DataProvider('errorPages')]
    public function test_the_copy_goes_through_the_translator(string $path): void
    {
        $html = (string) file_get_contents($path);

        $this->assertStringNotContainsString('Onderhoud', $html);
        $this->assertStringNotContainsString('Helaas', $html);
        $this->assertMatchesRegularExpression('/<h1>@lang\(/', $html);
    }

    public function test_every_locale_carries_the_strings_the_error_pages_ask_for(): void
    {
        $keys = [
            'Maintenance',
            'Sorry, this page does not exist (any more).',
            'We are briefly unreachable while the site is being worked on. Please try again later.',
        ];

        foreach (glob(__DIR__.'/../../stubs/template/lang/*.json') as $file) {
            $strings = json_decode((string) file_get_contents($file), true);

            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $strings, basename($file).' is missing "'.$key.'".');
                $this->assertNotSame('', trim((string) $strings[$key]), basename($file).' leaves "'.$key.'" empty.');
            }
        }
    }
}
