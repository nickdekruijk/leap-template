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
        // would only ever fill the active locale, and a test rendering another one would
        // pass against a tag no editor could have made.
        return [
            'name' => ['nl' => $word, 'en' => $word],
        ];
    }
}
