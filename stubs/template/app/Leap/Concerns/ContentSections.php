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
                Attribute::make('white_text')->switch()->label(['nl' => 'Witte tekst (voor op donkere achtergrond)', 'en' => 'White text (for dark backgrounds)'])->default(false),
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
                    'left' => 'Links vierkant',
                    'right' => 'Rechts vierkant',
                    'bottom wide' => 'Breedbeeld (onder tekst)',
                ]),
                Attribute::make('image')->media()->label(['nl' => 'Afbeelding(en)', 'en' => 'Image(s)']),
                Attribute::make('body')->richtext()->label(['nl' => 'Tekst', 'en' => 'Text'])->translatable(),
                Attribute::make('dark_background')->switch()->label(['nl' => 'Donkere achtergrond (witte tekst)', 'en' => 'Dark background (white text)'])->default(false),
                Attribute::make('background')->media(multiple: false)->label(['nl' => 'Achtergrondfoto (optioneel)', 'en' => 'Background photo (optional)']),
            ),
            Section::make('cta')->view('sections.default')->label(['nl' => 'Call to action', 'en' => 'Call to action'])->attributes(
                Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                Attribute::make('head')->required()->label(['nl' => 'Kop', 'en' => 'Heading'])->sectionTitle()->translatable(),
                Attribute::make('body')->richtext()->label(['nl' => 'Tekst', 'en' => 'Text'])->translatable(),
                Attribute::make('dark_background')->switch()->label(['nl' => 'Donkere achtergrond (witte tekst)', 'en' => 'Dark background (white text)'])->default(false),
                Attribute::make('background')->media(multiple: false)->label(['nl' => 'Achtergrondfoto (optioneel)', 'en' => 'Background photo (optional)']),
            ),
            Section::make('quote')->view('sections.default')->label(['nl' => 'Quote', 'en' => 'Quote'])->attributes(
                Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                Attribute::make('head')->required()->label(['nl' => 'Quote', 'en' => 'Quote'])->sectionTitle()->translatable(),
                Attribute::make('body')->label(['nl' => 'Van', 'en' => 'From'])->sectionTitle()->translatable(),
                Attribute::make('dark_background')->switch()->label(['nl' => 'Donkere achtergrond (witte tekst)', 'en' => 'Dark background (white text)'])->default(false),
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
