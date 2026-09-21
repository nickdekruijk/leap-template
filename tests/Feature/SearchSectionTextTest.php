<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use App\Livewire\Search;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\LeapTemplate\Tests\TestCase;
use ReflectionMethod;

/**
 * What the search counts as the text of a record.
 *
 * The SQL prefilter runs over the raw sections column: markup, structure, entities and
 * every locale at once. matchesLocale() is what turns that into "the words this visitor
 * can read on this page", and three things used to slip through it. All three are
 * decided in PHP, so they can be asserted without a database.
 */
class SearchSectionTextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The stub is a template file, not autoloaded code.
        if (! class_exists(Search::class, false)) {
            require_once dirname(__DIR__, 2).'/stubs/template/app/Livewire/Search.php';
        }

        config()->set('leap.locales', ['nl' => 'Nederlands', 'en' => 'English']);
    }

    private function record(array $sections, array $attributes = []): Model
    {
        $model = new class extends Model
        {
            protected $guarded = [];

            protected $casts = ['sections' => 'array'];
        };

        return $model->newInstance(array_merge([
            'title' => 'Een pagina',
            'description' => '',
            'sections' => $sections,
        ], $attributes));
    }

    private function finds(Model $record, string $term, string $locale = 'nl'): bool
    {
        $search = (new \ReflectionClass(Search::class))->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(Search::class, 'matchesLocale');

        return $method->invoke($search, $record, $term, $locale);
    }

    /**
     * A section switched off in the editor is not on the page. Finding the page by what
     * it says sends the visitor to something that is not there.
     */
    public function test_a_section_switched_off_is_not_searched(): void
    {
        $record = $this->record([
            ['_name' => 'default', 'active' => false, 'body' => ['nl' => '<p>Afgelast evenement</p>']],
        ]);

        $this->assertFalse($this->finds($record, 'afgelast'));
    }

    public function test_an_active_section_still_is(): void
    {
        $record = $this->record([
            ['_name' => 'default', 'active' => true, 'body' => ['nl' => '<p>Afgelast evenement</p>']],
        ]);

        $this->assertTrue($this->finds($record, 'afgelast'));
    }

    /**
     * Only some of a section's keys hold text. Searching the rest makes "left" a hit on
     * every page with an image beside its text.
     */
    public function test_structure_is_not_content(): void
    {
        $record = $this->record([
            ['_name' => 'default', 'image_position' => 'left', 'all_link' => '/sprekers', 'body' => ['nl' => '<p>Tekst</p>']],
        ]);

        $this->assertFalse($this->finds($record, 'left'));
        $this->assertFalse($this->finds($record, 'sprekers'));
        $this->assertTrue($this->finds($record, 'tekst'));
    }

    /**
     * The editor stores entities, so the words in the column are not the words anyone
     * types: "caf&eacute;" for café, and "&nbsp;" wherever a space was kept together.
     */
    public function test_entities_are_decoded_before_matching(): void
    {
        $record = $this->record([
            ['_name' => 'default', 'body' => ['nl' => '<p>Comit&eacute; van aanbeveling</p>']],
        ]);

        $this->assertTrue($this->finds($record, 'comité'));
    }

    public function test_a_phrase_survives_a_no_break_space(): void
    {
        $record = $this->record([
            ['_name' => 'default', 'body' => ['nl' => '<p>Een spreker&nbsp;aanvragen kan hier</p>']],
        ]);

        $this->assertTrue($this->finds($record, 'spreker aanvragen'));
    }

    /**
     * strip_tags() puts nothing in the tag's place, so an address written with line
     * breaks used to hold a word nobody types and hide the street.
     */
    public function test_a_tag_between_two_words_keeps_them_apart(): void
    {
        $record = $this->record([
            ['_name' => 'default', 'body' => ['nl' => '<p>Stichting X<br />Vlampijpstraat 84</p>']],
        ]);

        $this->assertTrue($this->finds($record, 'vlampijpstraat'));
        $this->assertFalse($this->finds($record, 'xvlampijpstraat'));
    }

    /**
     * strip_tags() keeps what is inside a script tag, so an embed pasted into the
     * content would make the page findable on its JavaScript.
     */
    public function test_a_script_block_is_not_content(): void
    {
        $record = $this->record([
            ['_name' => 'default', 'body' => ['nl' => '<p>Aanmelden</p><script>var brochure = 1;</script>']],
        ]);

        $this->assertFalse($this->finds($record, 'brochure'));
        $this->assertTrue($this->finds($record, 'aanmelden'));
    }

    /**
     * A quote section is read off the page like any other line.
     */
    public function test_a_quote_and_the_name_under_it_are_content(): void
    {
        $record = $this->record([
            ['_name' => 'quote', 'quote' => ['nl' => 'Vrijheid is werk'], 'name' => ['nl' => 'Iemand']],
        ]);

        $this->assertTrue($this->finds($record, 'vrijheid is werk'));
        $this->assertTrue($this->finds($record, 'iemand'));
    }

    /**
     * The locale scoping the rest of the suite covers stays intact: a section written
     * in both languages matches only in the one being read.
     */
    public function test_it_still_matches_the_active_locale_only(): void
    {
        $record = $this->record([
            ['_name' => 'default', 'body' => ['nl' => '<p>Onze missie</p>', 'en' => '<p>Our mission</p>']],
        ]);

        $this->assertTrue($this->finds($record, 'missie', 'nl'));
        $this->assertFalse($this->finds($record, 'mission', 'nl'));
        $this->assertTrue($this->finds($record, 'mission', 'en'));
    }

    /**
     * The SQL half, against a real table: the prefilter has to let the row through
     * before the refinement above ever sees it. A phrase broken by an entity is the
     * case that fails here rather than in PHP, because the row never arrives.
     */
    public function test_the_prefilter_lets_an_entity_through(): void
    {
        Schema::create('search_rows', function (Blueprint $table): void {
            $table->id();
            $table->text('title')->nullable();
            $table->text('description')->nullable();
            $table->text('sections')->nullable();
        });

        $model = new class extends Model
        {
            protected $table = 'search_rows';

            protected $guarded = [];

            public $timestamps = false;

            protected $casts = ['sections' => 'array'];
        };

        $model->newInstance([
            'title' => json_encode(['nl' => 'Aanvragen']),
            'description' => json_encode(['nl' => '']),
            'sections' => [['_name' => 'default', 'body' => ['nl' => '<p>Een spreker&nbsp;aanvragen kan hier</p>']]],
        ])->save();

        $model->newInstance([
            'title' => json_encode(['nl' => 'Beleid']),
            'description' => json_encode(['nl' => '']),
            'sections' => [['_name' => 'default', 'body' => ['nl' => '<p>Comit&eacute; van aanbeveling</p>']]],
        ])->save();

        $model->newInstance([
            'title' => json_encode(['nl' => 'Programma']),
            'description' => json_encode(['nl' => '']),
            'sections' => [['_name' => 'default', 'active' => false, 'body' => ['nl' => '<p>Afgelast evenement</p>']]],
        ])->save();

        $this->assertSame(['Aanvragen'], $this->titles($model, 'spreker aanvragen'));
        $this->assertSame(['Beleid'], $this->titles($model, 'comité'));
        $this->assertSame([], $this->titles($model, 'afgelast'));
    }

    /**
     * @return array<int, string>
     */
    private function titles(Model $model, string $term, string $locale = 'nl'): array
    {
        $search = (new \ReflectionClass(Search::class))->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(Search::class, 'matches');

        return $method->invoke($search, $model::query(), $term, $locale)
            ->map(fn (Model $row): string => json_decode($row->getRawOriginal('title'), true)['nl'])
            ->values()
            ->all();
    }
}
