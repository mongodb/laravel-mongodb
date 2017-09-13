<?php

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Group extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'groups';
    protected static $unguarded = true;

    public function users()
    {
        return $this->belongsToMany('User', 'users', 'groups', 'users', '_id', '_id', 'users');
    }
}
