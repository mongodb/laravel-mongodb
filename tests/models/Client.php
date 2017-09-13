<?php

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Client extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'clients';
    protected static $unguarded = true;

    public function users()
    {
        return $this->belongsToMany('User');
    }

    public function photo()
    {
        return $this->morphOne('Photo', 'imageable');
    }

    public function addresses()
    {
        return $this->hasMany('Address', 'data.client_id', 'data.client_id');
    }
}
