<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The generated App\Models\Page, as far as the commands care about it. Both
 * leap:content and leap:content-delete reach for this class by name, so the
 * tests have to supply one — and it has to be the same one for all of them,
 * because the first definition in the process wins.
 *
 * Deliberately a real file rather than an eval() in each test: an eval'd bare
 * class in one test file would leave a later test without softDeletes, and the
 * failure would depend on the order the suite happened to run in.
 *
 * Not PSR-4 autoloadable (App\ is the generated project's namespace, not this
 * package's); tests require_once it explicitly.
 */
class Page extends Model
{
    use SoftDeletes;

    protected $table = 'pages';

    protected $guarded = [];

    protected $casts = [
        'sections' => 'array',
    ];

    public $timestamps = false;
}
