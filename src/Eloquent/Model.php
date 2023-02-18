<?php

namespace Jenssegers\Mongodb\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;

if (config('database.default') === 'mongodb') {
    abstract class Model extends MongoModel
    {

    }
} else {
    abstract class Model extends BaseModel
    {

    }
}
