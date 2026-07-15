<?php

namespace App\Leap;

use App\Http\Controllers\PageController;
use App\Leap\Concerns\ContentSections;
use App\Models\Tag;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Classes\Section;
use NickDeKruijk\Leap\Resource;

class Page extends Resource
{
    use ContentSections;

    public function attributes()
    {
        return [
            Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
            Attribute::make('menuitem')->index(3)->switch()->label(['nl' => 'Toon in navigatie', 'en' => 'Show in navigation'], 'Nav')->default(true),
            Attribute::make('title')->index(1)->searchable()->required()->label(['nl' => 'Titel', 'en' => 'Title']),
            Attribute::make('parent')->tree($this)->label(['nl' => 'Subpagina van', 'en' => 'Subpage of']),
            Attribute::make('html_title')->searchable()
                ->label(['nl' => 'HTML-titel', 'en' => 'HTML title'])
                ->placeholder(['nl' => 'Leeg = paginatitel', 'en' => 'Empty = page title'])
                ->hint(['nl' => 'Voor SEO: de titel in de browsertab en zoekresultaten. Leeg laten gebruikt de paginatitel.', 'en' => 'For SEO: the title in the browser tab and search results. Leave empty to use the page title.']),
            Attribute::make('description')->textarea()
                ->label(['nl' => 'Omschrijving', 'en' => 'Description'])
                ->hint(['nl' => 'Voor SEO: de meta-omschrijving voor Google en social media (±150 tekens).', 'en' => 'For SEO: the meta description for Google and social media (~150 characters).']),
            Attribute::make('id')->indexOnly(),
            Attribute::make('slug')->index()->searchable()->unique()->slugFrom('title')->label('Slug'),
            Attribute::make('sort')->sortable(),
            Attribute::make('images')->media(),
            Attribute::make('sections')->label(['nl' => 'Secties', 'en' => 'Sections'])->sections(
                ...$this->contentSections(),
                ...$this->indexSections(),
            ),
        ];
    }

    /**
     * A card-row section per registered content type (config('leap.content')). Each
     * lets an editor drop a row of that type's cards onto any page — a limited teaser
     * that links to the overview, or the full overview itself. The section key is the
     * content key, so the frontend renders it through sections/{key}.blade.php when
     * that exists (the dated events view) or the generic sections/items.blade.php.
     *
     * @return array<int, Section>
     */
    protected function indexSections(): array
    {
        $sections = [];

        foreach (PageController::indexModels() as $key => $model) {
            $view = View::exists("sections.$key") ? "sections.$key" : 'sections.items';

            $attributes = [
                Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                Attribute::make('head')->label(['nl' => 'Kop', 'en' => 'Heading'])->sectionTitle()->translatable(),
            ];

            // Events can show upcoming, past, or both.
            if (method_exists($model, 'scopeFuture')) {
                $attributes[] = Attribute::make('period')->select()->default('upcoming')
                    ->label(['nl' => 'Periode', 'en' => 'Period'])
                    ->values([
                        'upcoming' => ['nl' => 'Aankomend', 'en' => 'Upcoming'],
                        'both' => ['nl' => 'Ook afgelopen', 'en' => 'Including past'],
                        'past' => ['nl' => 'Alleen afgelopen', 'en' => 'Past only'],
                    ]);
            }

            // The tag filter, only when tags are installed.
            if (class_exists(Tag::class)) {
                $attributes[] = Attribute::make('tag')->select()
                    ->label(['nl' => 'Filter op tag', 'en' => 'Filter by tag'])
                    ->hint(['nl' => 'Leeg = alle. Kies een tag om deze rij ertoe te beperken.', 'en' => 'Empty = all. Pick a tag to narrow this row to it.'])
                    ->values(['' => '—'] + Tag::orderBy('sort')->pluck('name', 'id')->all());
            }

            $attributes[] = Attribute::make('limit')->label(['nl' => 'Aantal (leeg = alles)', 'en' => 'Limit (empty = all)'])
                ->hint(['nl' => 'Leeg toont alles als grid (de overzichtspagina). Een getal maakt er een horizontale teaser-rij van.', 'en' => 'Empty shows everything as a grid (the overview page). A number makes it a horizontal teaser row.']);
            $attributes[] = Attribute::make('link')->label(['nl' => 'Link "bekijk alle" (optioneel)', 'en' => '"View all" link (optional)'])->translatable();
            $attributes[] = Attribute::make('link_label')->label(['nl' => 'Link-tekst', 'en' => 'Link label'])->translatable();

            $sections[] = Section::make($key)->view($view)
                ->label(['nl' => 'Kaartrij: '.Str::headline($key), 'en' => 'Card row: '.Str::headline($key)])
                ->attributes(...$attributes);
        }

        return $sections;
    }

    public $icon = 'fas-sitemap';

    public $priority = -2;

    public $orderBy = 'sort';

    public $active = 'active';

    public $title = [
        'nl' => 'Website pagina\'s',
        'en' => 'Website pages',
    ];
}
