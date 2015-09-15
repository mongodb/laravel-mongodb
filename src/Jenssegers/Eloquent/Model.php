<?php namespace Jenssegers\Eloquent;

use Jenssegers\Mongodb\Eloquent\HybridRelations;
use Jenssegers\Mongodb\Query\Builder as QueryBuilder;

abstract class Model extends \Illuminate\Database\Eloquent\Model {

    use HybridRelations;

}
