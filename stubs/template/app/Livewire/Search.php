<?php

namespace App\Livewire;

use App\Contracts\Content;
use App\Http\Controllers\PageController;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use NickDeKruijk\Leap\Leap;

/**
 * Live site search over the pages and every registered content type
 * (config('leap.content')). A plain Livewire class component (not a single-file/Volt
 * component) so it works on both Livewire 3 and 4. Mounted as <livewire:search />.
 *
 * Search is locale-aware: title, description and a listed item's intro are matched
 * against the active locale only, and section content is matched in SQL as a coarse
 * prefilter (the whole JSON blob, all locales) and then confirmed in PHP against the
 * active locale so a match in another language does not leak into the current one. Each
 * content result carries a type label (the overview page's title, e.g. "Nieuws") so the
 * mixed list reads.
 */
class Search extends Component
{
    public string $query = '';

    /**
     * Per-request memos. Instance properties rather than method statics: a static
     * outlives the request in a long-running worker, and would then hand out the path
     * a page had before its slug was edited.
     *
     * @var array<string, array<int, string>>
     */
    private array $urlCache = [];

    /**
     * @var array<string, array<string, bool>>
     */
    private array $columnCache = [];

    /**
     * A ceiling on how many rows one source may hand to the PHP refinement, so a term
     * that matches half the table does not load it. Not a result cap: the refinement
     * drops false positives from the coarse SQL prefilter, so this has to sit well
     * above RESULT_LIMIT or a better match further down the table is never seen.
     */
    private const CANDIDATE_LIMIT = 200;

    private const RESULT_LIMIT = 10;

    /**
     * The fields in a section that hold text a visitor reads. Everything else in there
     * is structure: image_position, all_link, tag, the media keys. Searching those as
     * well makes "left" a hit on every page with an image beside its text.
     *
     * @var array<int, string>
     */
    private const SECTION_TEXT_FIELDS = ['intro', 'head', 'body', 'text', 'quote', 'name', 'description'];

    #[Computed]
    public function results(): Collection
    {
        if (strlen($this->query) < 2) {
            return collect();
        }

        $locale = app()->getLocale();
        $term = mb_strtolower($this->query);

        // Pages
        $results = $this->matches(Page::active(), $term, $locale)
            ->map(fn ($page): array => [
                'title' => $page->getTranslation('title', $locale, false),
                'url' => $this->pageUrl($page->id),
                'excerpt' => Str::limit($page->metaDescription(), 120),
                'label' => null,
                'score' => $this->relevance($page, $term, $locale),
            ]);

        // Every registered content type
        foreach (PageController::indexModels() as $type => $model) {
            $label = PageController::overviewPage($type)?->title;

            $results = $results->concat(
                $this->matches($model::active(), $term, $locale)
                    ->map(fn ($item): array => [
                        'title' => $item->getTranslation('title', $locale, false),
                        'url' => PageController::itemUrl($item),
                        'excerpt' => Str::limit($item->metaDescription(), 120),
                        'label' => $label,
                        'score' => $this->relevance($item, $term, $locale),
                    ])
                    ->filter(fn (array $row): bool => $row['url'] !== null)
            );
        }

        // Sorted by how well each row matches, not by the order the sources happen to
        // be read in — a card actually called "Consent" belongs above three pages that
        // mention the word somewhere in a section. Ties keep the order they came in,
        // which is each source's own: pages by their menu order, releases by date.
        return $results->sortByDesc('score')->take(self::RESULT_LIMIT)->values();
    }

    /**
     * How well a record answers the term. A title beats prose, and an exact title beats
     * a title that merely contains it; matching only somewhere in a section body is the
     * weakest claim a result can make.
     *
     * @param  Model&Content  $item
     */
    private function relevance(Model $item, string $term, string $locale): int
    {
        $title = mb_strtolower((string) $item->getTranslation('title', $locale, false));

        if ($title === $term) {
            return 100;
        }

        if (str_starts_with($title, $term)) {
            return 80;
        }

        if (str_contains($title, $term)) {
            return 60;
        }

        foreach (['intro', 'description'] as $field) {
            if (! $this->hasColumn($item, $field)) {
                continue;
            }

            $value = mb_strtolower($this->plainText((string) $item->getTranslation($field, $locale, false)));

            if ($value !== '' && str_contains($value, $term)) {
                return 40;
            }
        }

        return 20;
    }

    /**
     * Candidate rows from a query whose active-locale content contains the term.
     *
     * A template rather than Builder<Model&Content>, because a Builder<Page> is not one:
     * Builder is invariant in its model.
     *
     * @template TModel of Model&Content
     *
     * @param  Builder<TModel>  $query
     * @return Collection<int, Model&Content>
     */
    private function matches($query, string $term, string $locale): Collection
    {
        $titleExpr = $this->localeColumnExpr('title', $locale);
        $descExpr = $this->localeColumnExpr('description', $locale);
        // A listed item's intro is its card text, and often the only prose it has; a
        // page has no intro column at all, so asking for one would fail the query.
        $introExpr = $this->hasColumn($query->getModel(), 'intro')
            ? $this->localeColumnExpr('intro', $locale)
            : null;

        // The editor writes entities, so the stored text may hold "spreker&nbsp;aanvragen"
        // where the visitor typed "spreker aanvragen", and "caf&eacute;" where they
        // typed "café". A space becomes % so the phrase survives whatever sits between
        // the words, and the encoded spelling is tried alongside the plain one. Both are
        // only the coarse prefilter: matchesLocale() decides.
        $patterns = array_unique([
            '%'.str_replace(' ', '%', $term).'%',
            '%'.str_replace(' ', '%', htmlentities($term, ENT_QUOTES | ENT_HTML5, 'UTF-8')).'%',
        ]);

        return $query
            ->where(function ($q) use ($titleExpr, $descExpr, $introExpr, $patterns) {
                foreach ($patterns as $pattern) {
                    $q->orWhereRaw("{$titleExpr} LIKE ?", [$pattern])
                        ->orWhereRaw("{$descExpr} LIKE ?", [$pattern])
                        // Coarse prefilter across every locale; refined per locale below.
                        ->orWhereRaw('LOWER(sections) LIKE ?', [$pattern]);

                    if ($introExpr) {
                        $q->orWhereRaw("{$introExpr} LIKE ?", [$pattern]);
                    }
                }
            })
            ->limit(self::CANDIDATE_LIMIT)
            ->get()
            ->filter(fn ($item): bool => $this->matchesLocale($item, $term, $locale))
            ->values();
    }

    /**
     * Whether a model's table has a column, cached per table for the request. The
     * searchable sources differ per model — every type has a title, only a listed
     * content item has an intro — and the schema is the one honest answer.
     */
    private function hasColumn(Model $model, string $column): bool
    {
        return $this->columnCache[$model->getTable()][$column] ??= Schema::hasColumn($model->getTable(), $column);
    }

    /**
     * SQL expression that yields a column's value in the given locale, lowercased.
     * Falls back to the raw column when the value is not translatable JSON, so the
     * query never chokes on a malformed value.
     */
    private function localeColumnExpr(string $column, string $locale): string
    {
        // $locale is interpolated into the SQL, so keep it to an ISO shape as
        // defence-in-depth even though it currently comes from app()->getLocale().
        if (! preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', $locale)) {
            $locale = app()->getLocale();
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            $extract = "json_extract(`{$column}`, '$.".$locale."')";

            return "LOWER(CASE WHEN json_valid(`{$column}`) THEN {$extract} ELSE `{$column}` END)";
        }

        $extract = "JSON_UNQUOTE(JSON_EXTRACT(`{$column}`, '$.".$locale."'))";

        return "LOWER(CASE WHEN JSON_VALID(`{$column}`) THEN {$extract} ELSE `{$column}` END)";
    }

    /**
     * Confirm the term appears in the record's active-locale content (title,
     * description or section fields), dropping cross-locale false positives from the
     * coarse SQL prefilter on the whole sections blob.
     *
     * @param  Model&Content  $item
     */
    private function matchesLocale(Model $item, string $term, string $locale): bool
    {
        // A plain attribute read, not getTranslation(): a page has no intro and would
        // throw. Null falls out at the is_string filter below.
        $haystack = [$item->title, $item->description, $item->intro ?? null];

        $haystack[] = $this->sectionText($item->sections, $locale);

        $text = mb_strtolower($this->plainText(implode(' ', array_filter($haystack, 'is_string'))));

        return str_contains($text, $term);
    }

    /**
     * The text of the sections a visitor in this locale is actually shown.
     *
     * Two things the raw column would get wrong. A section switched off in the editor
     * is not on the page, so finding the page by what it says sends the visitor to
     * something that is not there; HasSections::sections() drops those for the same
     * reason. And only some of a section's keys hold text at all, so the rest is
     * skipped rather than searched.
     */
    private function sectionText(mixed $sections, string $locale): string
    {
        $text = '';

        foreach ((array) $sections as $section) {
            if (! is_array($section) || ($section['active'] ?? true) === false) {
                continue;
            }

            foreach (self::SECTION_TEXT_FIELDS as $field) {
                // Leap::localize() is the rule HasSections uses to bring a field to the
                // view, fall back to the first translation included, so a match follows
                // what is on the screen.
                $value = Leap::localize($section[$field] ?? null, $locale);

                if (is_string($value) && $value !== '') {
                    $text .= ' '.$value;
                }
            }
        }

        return $text;
    }

    /**
     * HTML reduced to the words inside it.
     *
     * Tags become a space rather than nothing, because strip_tags() alone glues the
     * words on either side of a <br> into one: an address written as
     * "Vrijheidscolleges<br>Vlampijpstraat 84" holds a word no visitor will ever type.
     * Script and style blocks go first, contents and all, since strip_tags() keeps what
     * is inside them.
     *
     * Then the entities, or nobody typing "café" finds "caf&eacute;" and a phrase
     * broken by "&nbsp;" is never matched. The no-break space that decodes to is a
     * space to a reader but not to \s, so it is turned into one.
     */
    private function plainText(string $html): string
    {
        $text = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', ' ', $html);

        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $text)), ENT_QUOTES | ENT_HTML5);
        $text = strip_tags(str_replace('<', ' <', $text));

        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{a0}", ' ', $text)));
    }

    /**
     * A page result's URL, prefixed for the locale being read. Item results get theirs
     * from PageController::itemUrl(), which already carries the prefix; the page path
     * is built here and needs the same treatment, or every result on /nl points at the
     * default locale's copy of the page.
     */
    private function pageUrl(int $pageId): string
    {
        $path = $this->resolvePageUrl($pageId);

        return $path === '/' ? (Leap::localePrefix() ?: '/') : Leap::localePrefix().$path;
    }

    private function resolvePageUrl(int $pageId, array $seen = []): string
    {
        // Keyed by locale as well: the slugs differ per language, and a cache that
        // forgets that would hand one language's paths to another
        $locale = app()->getLocale();

        if (isset($this->urlCache[$locale][$pageId])) {
            return $this->urlCache[$locale][$pageId];
        }

        $page = Page::find($pageId, ['id', 'slug', 'parent']);
        if (! $page) {
            return '/';
        }

        // Guard against a corrupt parent chain that points back at itself.
        $seen[$pageId] = true;
        $slug = $page->getTranslation('slug', $locale, false);
        $url = $slug === '/'
            ? '/'
            : ($page->parent && ! isset($seen[$page->parent]) ? rtrim($this->resolvePageUrl($page->parent, $seen), '/').'/'.$slug : '/'.$slug);

        return $this->urlCache[$locale][$pageId] = $url;
    }

    public function render(): View
    {
        return view('livewire.search');
    }
}
