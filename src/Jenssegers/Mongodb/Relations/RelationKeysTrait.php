<?php
/**
 * Created by PhpStorm.
 * User: hooman
 * Date: 8/29/2015
 * Time: 2:06 AM
 */

namespace Jenssegers\Mongodb\Relations;


trait RelationKeysTrait
{
    /**
     * Get the key value of the parent's local key.
     *
     * @return mixed
     */
    public function getParentKey()
    {
        $value = $this->parent->getAttribute($this->localKey);
        if(\MongoId::isValid ($value)){
            return new \MongoId($value);
        }
        return $value;
    }
}