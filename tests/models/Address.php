<?php

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Address extends Eloquent
{

    protected static $unguarded = true;

    public function addresses()
    {
        return $this->embedsMany('Address');
    }
}
