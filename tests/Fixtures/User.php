<?php

namespace NickDeKruijk\LeapTemplate\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use NickDeKruijk\Leap\Traits\HasRoles;

/**
 * The user model the installer's admin-user step writes to. A host app has its own
 * App\Models\User; the tests point the auth provider at this one so leap:user has a
 * real Eloquent model without the temp skeleton having to be autoloadable.
 */
class User extends Authenticatable
{
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];
}
