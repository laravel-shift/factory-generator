<?php

namespace Tests\Fixtures\Models;

use Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class User extends Model
{
    protected $guarded = [];

    protected $hidden = ['password'];

    protected function shortName(): Attribute
    {
        $shortName = Str::before($this->name, ' ') . ' ' . Str::afterLast($this->name, ' ');
        
        return Attribute::make(get: fn() => $shortName);
    }
}
