<?php namespace Jenssegers\Eloquent;

use Jenssegers\Mongodb\Eloquent\HybridRelations;

abstract class Model extends \Illuminate\Database\Eloquent\Model
{

    use HybridRelations;
}
