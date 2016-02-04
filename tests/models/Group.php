<?php

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Group extends Eloquent
{

    protected $collection = 'groups';
    protected static $unguarded = true;

    public function users()
    {
        return $this->belongsToMany('User', null, 'groups', 'users');
    }
}
