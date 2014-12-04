<?php

class AddressCollection extends \Jenssegers\Mongodb\Eloquent\Collection
{

    public function notEmpty()
    {

        return ! $this->isEmpty();
    }
}
