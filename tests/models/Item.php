<?php

use Jenssegers\Mongodb\Model as Eloquent;

class Item extends Eloquent {

	protected $collection = 'items';
	protected static $unguarded = true;

	public function user()
    {
        return $this->belongsTo('User');
    }

    public function scopeSharp($query)
    {
    	return $query->where('type', 'sharp');
    }

}
