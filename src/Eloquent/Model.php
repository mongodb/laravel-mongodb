<?php

namespace Jenssegers\Mongodb\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;

$connection = config('database.default');
$driver     = config("database.connections.{$connection}.driver");

if ($driver === 'mongodb') {
    abstract class Model extends MongoModel
    {
        //
    }
} else {
    abstract class Model extends BaseModel
    {
        //
    }
}
