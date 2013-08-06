<?php

use Jenssegers\Mongodb\Model as Eloquent;

class User extends Eloquent {

	protected $collection = 'users';

	protected static $unguarded = true;

	public function books()
    {
        return $this->hasMany('Book', 'author_id');
    }

    public function items()
    {
        return $this->hasMany('Item');
    }

    public function role()
    {
        return $this->hasOne('Role');
    }

}