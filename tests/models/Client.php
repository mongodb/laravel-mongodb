<?php
use Jenssegers\Mongodb\Model as Eloquent;

class Client extends Eloquent {

	protected $collection = 'clients';

	protected static $unguarded = true;
	
	public function users()
	{
		return $this->belongsToMany('User');
	}
}