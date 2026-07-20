<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use NickDeKruijk\LeapTemplate\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * The views are written in English, so English needs no file and every other language is a
 * plain translation of those keys. Nothing kept the two in step: the shipped file used to
 * cover 9 of 29 strings, so a site in that language showed a third of its interface in the
 * source language — the search box, the video section and the slider among them.
 */
class TranslationsTest extends TestCase
{
    private const STUBS = __DIR__.'/../../stubs';

    /**
     * Content type names, which reach __() through the '{{ Models }}' placeholder in a
     * seeder and so cannot be found by scanning. A site does need them: the name is what
     * the overview page is called and what its slug is derived from, so an untranslated
     * one leaves a Dutch site with News at /news.
     *
     * News and Events are what the template installs by default; Projects is here because
     * it is the common third and costs nothing to have ready.
     *
     * @var array<int, string>
     */
    private const CONTENT_TYPE_NAMES = ['News', 'Events', 'Projects'];

    /**
     * Every string the templates hand to __() or @lang(), including the parameterised ones.
     *
     * @return array<int, string>
     */
    private static function sourceStrings(): array
    {
        $found = self::CONTENT_TYPE_NAMES;

        foreach (Finder::create()->files()->in(self::STUBS)->notPath('template/lang') as $file) {
            /** @var SplFileInfo $file */
            preg_match_all(
                '/(?:__|@lang)\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/',
                (string) file_get_contents($file->getPathname()),
                $matches,
            );
            $found = array_merge($found, $matches[1]);
        }

        // A seeder names its overview page with __('{{ Models }}'), a placeholder the
        // generator substitutes — as a literal it translates to nothing.
        $found = array_values(array_filter(
            array_unique($found),
            fn (string $string): bool => ! str_contains($string, '{{'),
        ));
        sort($found);

        return $found;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function shippedLanguages(): array
    {
        $cases = [];

        foreach (glob(self::STUBS.'/template/lang/*.json') ?: [] as $file) {
            $cases[basename($file, '.json')] = [$file];
        }

        return $cases;
    }

    #[DataProvider('shippedLanguages')]
    public function test_a_shipped_language_translates_every_source_string(string $file): void
    {
        $translations = json_decode((string) file_get_contents($file), true);

        $this->assertIsArray($translations, basename($file).' is not valid JSON.');

        $missing = array_diff(self::sourceStrings(), array_keys($translations));

        $this->assertSame(
            [],
            array_values($missing),
            basename($file).' is missing translations, so these stay English on that site: '.implode(' | ', $missing),
        );
    }

    /**
     * A key no template asks for is a leftover — usually a source string that was reworded,
     * leaving the translation behind to rot unnoticed.
     */
    #[DataProvider('shippedLanguages')]
    public function test_a_shipped_language_translates_nothing_that_no_longer_exists(string $file): void
    {
        $translations = json_decode((string) file_get_contents($file), true);
        $stale = array_diff(array_keys((array) $translations), self::sourceStrings());

        $this->assertSame(
            [],
            array_values($stale),
            basename($file).' translates strings no template uses: '.implode(' | ', $stale),
        );
    }

    /**
     * English is the source, so a lang/en.json would translate the keys into themselves and
     * quietly become the place where a stale copy of the interface lives.
     */
    public function test_no_english_translation_file_is_shipped(): void
    {
        $this->assertFileDoesNotExist(self::STUBS.'/template/lang/en.json');
    }
}
