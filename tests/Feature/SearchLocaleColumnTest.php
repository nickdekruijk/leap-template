<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use App\Livewire\Search;
use NickDeKruijk\LeapTemplate\Tests\TestCase;
use ReflectionMethod;

/**
 * Search reads a translatable column out of its JSON per driver: json_extract on
 * sqlite, JSON_UNQUOTE(JSON_EXTRACT(...)) on MySQL. The rest of the suite runs on
 * sqlite, so the MySQL branch — the one every real deployment takes — was never
 * executed. Building the expression needs no server, so both branches can be
 * asserted as strings.
 */
class SearchLocaleColumnTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The stub is a template file, not autoloaded code; load it once so its
        // private query builder can be exercised directly.
        if (! class_exists(Search::class, false)) {
            require_once dirname(__DIR__, 2).'/stubs/template/app/Livewire/Search.php';
        }
    }

    private function expr(string $column, string $locale, string $driver): string
    {
        config()->set('database.default', $driver);
        config()->set('database.connections.'.$driver, match ($driver) {
            'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            default => ['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'test', 'username' => 'root', 'password' => '', 'prefix' => ''],
        });

        $search = (new \ReflectionClass(Search::class))->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(Search::class, 'localeColumnExpr');

        return $method->invoke($search, $column, $locale);
    }

    public function test_sqlite_uses_json_extract(): void
    {
        $this->assertSame(
            "LOWER(CASE WHEN json_valid(`title`) THEN json_extract(`title`, '$.nl') ELSE `title` END)",
            $this->expr('title', 'nl', 'sqlite'),
        );
    }

    /**
     * JSON_EXTRACT returns a quoted JSON string on MySQL, so the value would come
     * back as "\"Over ons\"" and never match a search term. JSON_UNQUOTE is what
     * makes the comparison work.
     */
    public function test_mysql_unquotes_the_extracted_value(): void
    {
        $this->assertSame(
            "LOWER(CASE WHEN JSON_VALID(`title`) THEN JSON_UNQUOTE(JSON_EXTRACT(`title`, '$.nl')) ELSE `title` END)",
            $this->expr('title', 'nl', 'mysql'),
        );
    }

    /**
     * A column holding a plain string rather than translatable JSON must still be
     * searchable — hence the CASE, on both drivers.
     */
    public function test_both_drivers_fall_back_to_the_raw_column(): void
    {
        $this->assertStringContainsString('ELSE `description` END', $this->expr('description', 'en', 'sqlite'));
        $this->assertStringContainsString('ELSE `description` END', $this->expr('description', 'en', 'mysql'));
    }

    /**
     * The locale is interpolated into the SQL. It comes from app()->getLocale()
     * today, but the guard is what keeps that from becoming an injection point if
     * it is ever fed from a URL segment.
     */
    public function test_a_locale_that_is_not_an_iso_code_is_replaced(): void
    {
        app()->setLocale('en');

        $expr = $this->expr('title', "nl') OR 1=1 --", 'mysql');

        $this->assertStringNotContainsString('1=1', $expr);
        $this->assertStringContainsString("'$.en'", $expr);
    }

    public function test_a_regional_locale_is_accepted(): void
    {
        $this->assertStringContainsString("'$.pt-br'", $this->expr('title', 'pt-br', 'mysql'));
    }
}
