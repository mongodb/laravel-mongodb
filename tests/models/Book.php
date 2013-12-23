<?php

use Jenssegers\Mongodb\Model as Eloquent;

class Book extends Eloquent {

	protected $collection = 'books';
	protected static $unguarded = true;
	protected $primaryKey = 'title';

    public function author()
    {
        return $this->belongsTo('User', 'author_id');
    }

    public function mysqlAuthor()
    {
        return $this->belongsTo('MysqlUser', 'author_id');
    }
}
