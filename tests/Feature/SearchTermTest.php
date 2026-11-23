<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use App\Livewire\Search;
use NickDeKruijk\LeapTemplate\Tests\TestCase;
use ReflectionMethod;

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
        if (! class_exists(Search::class, false)) {
            require_once dirname(__DIR__, 2).'/stubs/template/app/Livewire/Search.php';
        }
    }

    private function search(string $query): Search
    {
        $search = (new \ReflectionClass(Search::class))->newInstanceWithoutConstructor();
        $search->query = $query;

        return $search;
    }

    public function test_the_space_around_the_words_is_no_part_of_the_term(): void
    {
        $method = new ReflectionMethod(Search::class, 'term');

        $this->assertSame('popronde 2025', $method->invoke($this->search('  Popronde 2025 ')));
    }

    /**
     * Two spaces and a letter are one character to look for: too short to search, so
     * nothing is queried.
     */
    public function test_a_term_that_is_short_once_trimmed_finds_nothing(): void
    {
        $this->assertTrue($this->search('  a ')->results()->isEmpty());
    }
}
