<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use App\Support\SiteSearch;
use NickDeKruijk\LeapTemplate\Tests\TestCase;

/**
 * What the search looks for, given what was typed. A space before or after the words
 * used to be part of the term, and since no title starts with one, every search with it
 * came back "No results found".
 */
class SearchTermTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The stub is a template file, not autoloaded code.
        if (! class_exists(SiteSearch::class, false)) {
            require_once dirname(__DIR__, 2).'/stubs/template/app/Support/SiteSearch.php';
        }
    }

    public function test_the_space_around_the_words_is_no_part_of_the_term(): void
    {
        $this->assertSame('popronde 2025', SiteSearch::term('  Popronde 2025 '));
    }

    /**
     * Two spaces and a letter are one character to look for: too short to search, so
     * nothing is queried.
     */
    public function test_a_term_that_is_short_once_trimmed_finds_nothing(): void
    {
        $this->assertTrue((new SiteSearch)->results('  a ')->isEmpty());
    }
}
