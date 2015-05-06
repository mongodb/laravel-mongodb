<?php

use Jenssegers\Mongodb\Model as Eloquent;

class Address extends Eloquent {

    protected static $unguarded = true;
    protected $hidden = array('ownership');

    public function addresses()
    {
        return $this->embedsMany('Address');
    }

}
