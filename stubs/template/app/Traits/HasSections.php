<?php

namespace App\Traits;

use ArrayObject;
use Illuminate\Support\Collection;
use NickDeKruijk\Leap\Models\Mediable;

trait HasSections
{
    /**
     * Return the sections of a page as a collection
     *
     * @param  string  $attribute  The model attribute that has the sections, usualy a json column in the database
     */
    public function sections($attribute = 'sections'): Collection
    {
        $sections = $this->$attribute;

        // Get all media for each section
        foreach (Mediable::with('media')->where('mediable_type', self::class)->where('mediable_id', $this->id)->get() as $media) {
            $modelAttribute = explode('.', $media->mediable_attribute);
            if ($modelAttribute[0] == $attribute) {
                $sections[$modelAttribute[1]][$modelAttribute[2]] = ($sections[$modelAttribute[1]][$modelAttribute[2]] ?? new Collection)->concat([$media->media]);
            }
        }

        // Convert each section to an ArrayObject, resolving per-locale fields to the current locale
        $locales = config('leap.locales');
        $localeKeys = $locales ? array_keys($locales) : null;
        foreach ($sections ?: [] as $key => $section) {
            foreach ($section as $field => $value) {
                // A per-locale array (['nl' => …, 'en' => …]); media fields are Collections
                // (objects, not arrays) and are skipped. With leap.locales set the keys must
                // be known locales; when it is null (monolingual) any associative array is
                // treated as a translation set — the seeders still ship every locale, so the
                // extras are collapsed to the current locale rather than rendered raw.
                if (! is_array($value) || $value === []) {
                    continue;
                }
                $isPerLocale = $localeKeys !== null
                    ? ! array_diff(array_keys($value), $localeKeys)
                    : array_keys($value) !== range(0, count($value) - 1);
                if ($isPerLocale) {
                    $section[$field] = $value[app()->getLocale()] ?? (reset($value) ?: '');
                }
            }
            $sections[$key] = new ArrayObject($section);
            $sections[$key]->setFlags(ArrayObject::STD_PROP_LIST | ArrayObject::ARRAY_AS_PROPS);
        }

        // Sort sections as collection
        $sections = collect($sections)->sortBy('_sort');

        // Determine _first and _last values
        $previousName = null;
        $previousKey = null;
        foreach ($sections as $key => $section) {
            $sections[$key]['_first'] = $section['_name'] != $previousName;
            $sections[$key]['_last'] = true;
            if ($previousName && ! $sections[$key]['_first']) {
                $sections[$previousKey]['_last'] = false;
            }
            $previousName = $section['_name'];
            $previousKey = $key;
        }

        return $sections;
    }
}
