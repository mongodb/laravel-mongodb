<?php

use Jenssegers\Mongodb\Model as Eloquent;

class User extends Eloquent {

	protected $collection = 'users';

	protected static $unguarded = true;

	public function phone()
    {
        return $this->hasOne('Phone');
    }

}