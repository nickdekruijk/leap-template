<?php

namespace App\Leap;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Resource;

class Tag extends Resource
{
    public $model = \App\Models\Tag::class;

    public function attributes(): array
    {
        return [
            Attribute::make('name')->index(1)->searchable()->required()->label(['nl' => 'Naam', 'en' => 'Name']),
            Attribute::make('sort')->sortable(),
            Attribute::make('id')->indexOnly(),
        ];
    }

    public $icon = 'fas-tags';

    // Last of the content modules, just before the file manager (priority 50), with room
    // to slot other modules in between.
    public $priority = 40;

    public $orderBy = 'sort';

    public $title = [
        'nl' => 'Tags',
        'en' => 'Tags',
    ];
}
