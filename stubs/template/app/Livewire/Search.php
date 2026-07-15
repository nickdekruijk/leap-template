<?php

namespace App\Livewire;

use App\Http\Controllers\PageController;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Live site search over the pages and every registered content type
 * (config('leap.content')). A plain Livewire class component (not a single-file/Volt
 * component) so it works on both Livewire 3 and 4. Mounted as <livewire:search />.
 *
 * Search is locale-aware: title and description are matched against the active locale
 * only, and section content is matched in SQL as a coarse prefilter (the whole JSON
 * blob, all locales) and then confirmed in PHP against the active locale so a match in
 * another language does not leak into the current one. Each content result carries a
 * type label (the overview page's title, e.g. "Nieuws") so the mixed list reads.
 */
class Search extends Component
{
    public string $query = '';

    /**
     * How many candidate rows to pull per source before the per-locale PHP refinement.
     */
    private const CANDIDATE_LIMIT = 30;

    private const RESULT_LIMIT = 10;

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
            ->map(fn (Page $page): array => [
                'title' => $page->getTranslation('title', $locale, false),
                'url' => $this->resolvePageUrl($page->id),
                'excerpt' => Str::limit($page->getTranslation('description', $locale, false), 120),
                'label' => null,
            ]);

        // Every registered content type
        foreach (PageController::indexModels() as $type => $model) {
            $label = PageController::overviewPage($type)?->title;

            $results = $results->concat(
                $this->matches($model::active(), $term, $locale)
                    ->map(fn (Model $item): array => [
                        'title' => $item->getTranslation('title', $locale, false),
                        'url' => PageController::itemUrl($item),
                        'excerpt' => Str::limit($item->getTranslation('description', $locale, false), 120),
                        'label' => $label,
                    ])
                    ->filter(fn (array $row): bool => $row['url'] !== null)
            );
        }

        return $results->take(self::RESULT_LIMIT)->values();
    }

    /**
     * Candidate rows from a query whose active-locale content contains the term.
     *
     * @param  Builder<Model>  $query
     * @return Collection<int, Model>
     */
    private function matches($query, string $term, string $locale): Collection
    {
        $titleExpr = $this->localeColumnExpr('title', $locale);
        $descExpr = $this->localeColumnExpr('description', $locale);

        return $query
            ->where(function ($q) use ($titleExpr, $descExpr, $term) {
                $q->whereRaw("{$titleExpr} LIKE ?", ["%{$term}%"])
                    ->orWhereRaw("{$descExpr} LIKE ?", ["%{$term}%"])
                    // Coarse prefilter across every locale; refined per locale below.
                    ->orWhereRaw('LOWER(sections) LIKE ?', ["%{$term}%"]);
            })
            ->limit(self::CANDIDATE_LIMIT)
            ->get()
            ->filter(fn (Model $item): bool => $this->matchesLocale($item, $term, $locale))
            ->take(self::RESULT_LIMIT)
            ->values();
    }

    /**
     * SQL expression that yields a column's value in the given locale, lowercased.
     * Falls back to the raw column when the value is not translatable JSON, so the
     * query never chokes on a malformed value.
     */
    private function localeColumnExpr(string $column, string $locale): string
    {
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
     */
    private function matchesLocale(Model $item, string $term, string $locale): bool
    {
        $haystack = [$item->title, $item->description];

        foreach ((array) $item->sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            foreach ($section as $key => $value) {
                // Skip structural keys (_name, _view, _title, …)
                if (is_string($key) && str_starts_with($key, '_')) {
                    continue;
                }
                if (is_array($value)) {
                    $value = $value[$locale] ?? '';
                }
                if (is_string($value) && $value !== '') {
                    $haystack[] = $value;
                }
            }
        }

        $text = mb_strtolower(strip_tags(implode(' ', array_filter($haystack, 'is_string'))));

        return str_contains($text, $term);
    }

    private function resolvePageUrl(int $pageId): string
    {
        static $cache = [];

        if (isset($cache[$pageId])) {
            return $cache[$pageId];
        }

        $page = Page::find($pageId, ['id', 'slug', 'parent']);
        if (! $page) {
            return '/';
        }

        $slug = $page->getTranslation('slug', app()->getLocale(), false);
        $url = $slug === '/'
            ? '/'
            : ($page->parent ? rtrim($this->resolvePageUrl($page->parent), '/').'/'.$slug : '/'.$slug);

        return $cache[$pageId] = $url;
    }

    public function render(): View
    {
        return view('livewire.search');
    }
}
