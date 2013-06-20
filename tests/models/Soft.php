<?php

use Jenssegers\Mongodb\Model as Eloquent;

class Soft extends Eloquent {

	protected $collection = 'soft';
	protected $softDelete = true;

}