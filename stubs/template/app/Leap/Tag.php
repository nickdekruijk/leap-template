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

    public $priority = 1;

    public $orderBy = 'sort';

    public $title = [
        'nl' => 'Tags',
        'en' => 'Tags',
    ];
}
