<?php

use Jenssegers\Mongodb\Model as Eloquent;

class Address extends Eloquent {

    protected static $unguarded = true;

    public function addresses()
    {
        return $this->embedsMany('Address');
    }

    public function newCollection(array $models = array())
    {

        return new AddressCollection($models);
    }

}
