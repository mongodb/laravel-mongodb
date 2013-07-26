<?php

use Jenssegers\Mongodb\Model as Eloquent;

class Book extends Eloquent {

	protected $collection = 'books';

	protected static $unguarded = true;

	protected $primaryKey = 'title';
}