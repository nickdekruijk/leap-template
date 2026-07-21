<?php

namespace App\Leap\Concerns;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Classes\Section;

/**
 * The section blocks a content page is built from. Shared by Page and every listed
 * content type (news, events, projects …), so an item is composed exactly like a page
 * and the frontend renders them through the same views under resources/views/sections.
 *
 * The Page resource adds its index sections (the card rows for each content type) on
 * top of these; a content-type resource uses these as-is.
 */
trait ContentSections
{
    /**
     * @return array<int, Section>
     */
    protected function contentSections(): array
    {
        return [
            Section::make('slide')->label(['nl' => 'Slide (carousel)', 'en' => 'Slide (carousel)'])->attributes(
                Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                Attribute::make('white_text')->switch()->label(['nl' => 'Witte tekst', 'en' => 'White text'])->default(true)
                    ->hint([
                        'nl' => 'Staat standaard aan: een slide zonder afbeelding krijgt een donker verloop, en een slide-afbeelding is meestal donker. Zet uit bij een lichte afbeelding.',
                        'en' => 'On by default: a slide without an image gets a dark gradient, and a slide image is usually dark. Switch off for a light image.',
                    ]),
                Attribute::make('head')->label(['nl' => 'Kop', 'en' => 'Heading'])->sectionTitle()->translatable(),
                Attribute::make('image')->media(multiple: false)->required()->label(['nl' => 'Afbeelding of video (.mp4)', 'en' => 'Image or video (.mp4)']),
                Attribute::make('body')->richtext()->label(['nl' => 'Tekst', 'en' => 'Text'])->translatable(),
            ),
            Section::make('default')->label(['nl' => 'Tekst met afbeelding', 'en' => 'Text with image'])->attributes(
                Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                Attribute::make('menuitem')->switch()->label(['nl' => 'Kop tonen in navigatie', 'en' => 'Show heading in navigation'])->default(false),
                Attribute::make('head')->required()->label(['nl' => 'Kop', 'en' => 'Heading'])->sectionTitle()->translatable(),
                Attribute::make('image_position')->default('right')->label(['nl' => 'Positie afbeelding', 'en' => 'Image position'])->select()->values([
                    'none' => 'Geen afbeelding',
                    'left' => 'Links',
                    'right' => 'Rechts',
                    'bottom wide' => 'Breedbeeld (onder tekst)',
                ]),
                Attribute::make('image')->media()->label(['nl' => 'Afbeelding(en)', 'en' => 'Image(s)']),
                Attribute::make('body')->richtext()->label(['nl' => 'Tekst', 'en' => 'Text'])->translatable(),
                Attribute::make('white_text')->switch()->label(['nl' => 'Witte tekst', 'en' => 'White text'])->default(false)
                    ->hint([
                        'nl' => 'Voor een sectie met een donkere achtergrondfoto. Zonder achtergrondfoto krijgt de sectie een donker verloop. Links worden ook wit.',
                        'en' => 'For a section with a dark background photo. Without a background photo the section gets a dark gradient. Links turn white too.',
                    ]),
                Attribute::make('background')->media(multiple: false)->label(['nl' => 'Achtergrondfoto (optioneel)', 'en' => 'Background photo (optional)']),
            ),
            Section::make('cta')->view('sections.default')->label(['nl' => 'Call to action', 'en' => 'Call to action'])->attributes(
                Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                Attribute::make('head')->required()->label(['nl' => 'Kop', 'en' => 'Heading'])->sectionTitle()->translatable(),
                Attribute::make('body')->richtext()->label(['nl' => 'Tekst', 'en' => 'Text'])->translatable(),
                Attribute::make('white_text')->switch()->label(['nl' => 'Witte tekst', 'en' => 'White text'])->default(false)
                    ->hint([
                        'nl' => 'Voor een sectie met een donkere achtergrondfoto. Zonder achtergrondfoto krijgt de sectie een donker verloop. Links worden ook wit.',
                        'en' => 'For a section with a dark background photo. Without a background photo the section gets a dark gradient. Links turn white too.',
                    ]),
                Attribute::make('background')->media(multiple: false)->label(['nl' => 'Achtergrondfoto (optioneel)', 'en' => 'Background photo (optional)']),
            ),
            Section::make('quote')->view('sections.default')->label(['nl' => 'Quote', 'en' => 'Quote'])->attributes(
                Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                Attribute::make('head')->required()->label(['nl' => 'Quote', 'en' => 'Quote'])->sectionTitle()->translatable(),
                Attribute::make('body')->label(['nl' => 'Van', 'en' => 'From'])->sectionTitle()->translatable(),
                Attribute::make('white_text')->switch()->label(['nl' => 'Witte tekst', 'en' => 'White text'])->default(false)
                    ->hint([
                        'nl' => 'Voor een sectie met een donkere achtergrondfoto. Zonder achtergrondfoto krijgt de sectie een donker verloop. Links worden ook wit.',
                        'en' => 'For a section with a dark background photo. Without a background photo the section gets a dark gradient. Links turn white too.',
                    ]),
                Attribute::make('background')->media(multiple: false)->label(['nl' => 'Achtergrondfoto (optioneel)', 'en' => 'Background photo (optional)']),
            ),
            Section::make('video')->label(['nl' => 'Video (breedbeeld)', 'en' => 'Video (full width)'])->attributes(
                Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                Attribute::make('head')->label(['nl' => 'Kop (voor schermlezers)', 'en' => 'Heading (for screen readers)'])->sectionTitle()->translatable(),
                Attribute::make('video_id')->required()
                    ->label(['nl' => 'Video-ID (YouTube of Vimeo)', 'en' => 'Video ID (YouTube or Vimeo)'])
                    ->hint([
                        'nl' => 'YouTube: het deel na "?v=" in de URL, bijv. dQw4w9WgXcQ. Vimeo: het nummer in de URL, bijv. 1084537.',
                        'en' => 'YouTube: the part after "?v=" in the URL, e.g. dQw4w9WgXcQ. Vimeo: the number in the URL, e.g. 1084537.',
                    ]),
                Attribute::make('image')->media(multiple: false)
                    ->label(['nl' => 'Poster-afbeelding (optioneel)', 'en' => 'Poster image (optional)'])
                    ->hint([
                        'nl' => 'Leeg = de poster wordt automatisch bij YouTube/Vimeo opgehaald en lokaal opgeslagen. Wordt getoond tot er op play wordt geklikt; de video zelf laadt pas daarna.',
                        'en' => 'Empty = the poster is fetched from YouTube/Vimeo and stored locally. Shown until play is clicked; the video itself only loads after that.',
                    ]),
            ),
            // Renders the cookie registry from config('leap.consent') on the privacy
            // page, so it cannot drift away from the cookies the site actually sets.
            Section::make('cookies')->label(['nl' => 'Cookie-overzicht', 'en' => 'Cookie overview'])->attributes(
                Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                Attribute::make('head')->label(['nl' => 'Kop', 'en' => 'Heading'])->sectionTitle()->translatable(),
                Attribute::make('body')->richtext()->label(['nl' => 'Inleidende tekst', 'en' => 'Introduction'])->translatable(),
            ),
        ];
    }
}
