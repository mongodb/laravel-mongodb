<?php

use Jenssegers\Mongodb\Model as Eloquent;

class Role extends Eloquent {

	protected $collection = 'roles';
	protected static $unguarded = true;

    public function user()
    {
    	return $this->belongsTo('User');
    }

    public function mysqlUser()
    {
    	return $this->belongsTo('MysqlUser');
    }

}
