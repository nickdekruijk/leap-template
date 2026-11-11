<?php

namespace App\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use NickDeKruijk\Leap\Models\Media;

/**
 * What the page engine reads from a page and from every content type in
 * config('leap.content'): PageController, the live search and the item views all handle
 * them through this, without knowing the class.
 *
 * Leap's traits provide all of it (HasTranslations, HasSections, HasSlug, HasMedia and
 * HasDocumentMeta), plus an active() scope on the model, so a model only says
 * `implements Content`. The engine never checks for it at runtime: it is here for
 * static analysis, and a model generated before it existed keeps working without it.
 *
 * @phpstan-require-extends Model
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $slug
 * @property string|null $description
 * @property array<int|string, array<string, mixed>>|null $sections
 * @property CarbonInterface|null $updated_at
 * @property string|null $url The URL of the detail page. Not a column: PageController::items()
 *                            sets it on each item it lists, for the card to link to.
 *
 * @method static Builder<static&Model> active() The frontend set: what a visitor may see, in order
 * @method MorphToMany<Media, Model> media()
 * @method mixed getTranslation(string $key, string $locale, bool $useFallbackLocale = true)
 * @method string metaDescription()
 */
interface Content {}
