<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $word = Str::ucfirst(fake()->unique()->word());

        // The name is translatable, so make one the way a real tag looks: a plain string
        // fills only the active locale, and a test rendering another one would pass against
        // a tag no editor could have made. The site's own locales, not a hardcoded nl/en —
        // seeding a language it does not have is litter nobody ever sees or deletes.
        $locales = array_keys(config('leap.locales') ?: [app()->getLocale() => null]);

        return [
            'name' => array_fill_keys($locales, $word),
        ];
    }
}
