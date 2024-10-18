<?php

namespace MongoDB\Laravel\Tests\Scout;

use Laravel\Scout\Searchable;
use MongoDB\Laravel\Eloquent\Model;

class Post extends Model
{
    use Searchable;
}
