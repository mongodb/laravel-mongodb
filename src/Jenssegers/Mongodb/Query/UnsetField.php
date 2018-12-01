<?php

namespace Jenssegers\Mongodb\Query;

use MongoDB\BSON\Serializable;

class UnsetField implements Serializable
{
    public function bsonSerialize()
    {
        throw new \LogicException('UnsetField is not BSON serializable');
    }
}
