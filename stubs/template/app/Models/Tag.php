<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

/**
 * A term a content item is labelled with — one shared, polymorphic vocabulary across
 * every listed type. The name is translatable and is the identity; there is no slug
 * column: the overview filter slugifies the active-locale name on the fly, so a
 * renamed tag never leaves a stale slug behind.
 */
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    use HasTranslations;

    protected $fillable = [
        'name',
        'sort',
    ];

    public $translatable = [
        'name',
    ];

    /**
     * The tag as it appears in a URL: the active-locale name, slugified. Not stored.
     *
     * @return Attribute<string, never>
     */
    protected function slug(): Attribute
    {
        return Attribute::get(fn (): string => Str::slug($this->name));
    }

    /**
     * Find a tag by its name in the first given locale, or make it. The name is the
     * identity, so this is the one place that decides whether a term is new.
     *
     * @param  array<string, string>|string  $name  The name per locale, or a plain string
     */
    public static function named(array|string $name): self
    {
        $locale = is_array($name) ? array_key_first($name) : app()->getLocale();
        $value = is_array($name) ? reset($name) : $name;

        return static::firstWhere("name->{$locale}", $value) ?? static::create(['name' => $name]);
    }
}
