<?php

namespace Tests\Concerns;

use Illuminate\Support\Str;

/**
 * Paths to a content type's overview and its items.
 *
 * The seeders name an overview page after the translated type, so it lives at /nieuws
 * on a Dutch site and /news on an English one. A test that writes /news therefore
 * passes or fails on which language the site happens to be in. These ask for the path
 * instead.
 *
 * A trait rather than a method on TestCase: every Laravel app already owns its
 * TestCase, and the template should not have to overwrite it to hand this over.
 */
trait ResolvesContentPaths
{
    /**
     * @param  string  $type  The English title the seeder translates, e.g. 'News'.
     */
    protected function overviewPath(string $type): string
    {
        return '/'.Str::slug(__($type));
    }

    /**
     * The detail path of an item under that overview, e.g. /nieuws/mijn-bericht.
     */
    protected function itemPath(string $type, string $slug): string
    {
        return $this->overviewPath($type).'/'.$slug;
    }
}
