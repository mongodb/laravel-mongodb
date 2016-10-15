<?php

namespace Moloquent\Relations;

use Illuminate\Database\Eloquent\Collection;

/**
 * Class HasOneOrManyTrait.
 *
 * @property $this parent Moloquent\Relations
 * @property $this localKey
 * @property $this related
 */
trait HasOneOrManyTrait
{
    /**
     * Get all of the primary keys for an array of models.
     *
     * @param array  $models
     * @param string $key
     *
     * @return array
     */
    protected function getKeys(array $models, $key = null)
    {
        return array_unique(array_values(array_map(function ($model) use ($key) {
            $id = $key ? $model->getAttribute($key) : $model->getKey();

            if ($this->related->useMongoId()) {
                $model->setRelationCast($key);

                return $model->castAttribute($key, $id, 'set');
            }

            return $id;
        }, $models)));
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param \Illuminate\Database\Eloquent\Collection $results
     *
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        $dictionary = [];
        $foreign = $this->getPlainForeignKey();
        // First we will create a dictionary of models keyed by the foreign key of the
        // relationship as this will allow us to quickly access all of the related
        // models without having to do nested looping which will be quite slow.
        foreach ($results as $result) {
            $dictionary[(string) $result->{$foreign}][] = $result;
        }

        return $dictionary;
    }

    /**
     * Get the key value of the parent's local key.
     *
     * @return mixed
     */
    public function getParentKey()
    {
        $id = $this->parent->getAttribute($this->localKey);
        $this->related->setRelationCast($this->localKey);
        if ($this->related->useMongoId()
            && $this->related->hasCast($this->localKey, null, 'set')) {
            $id = $this->related->castAttribute($this->localKey, $id, 'set');
        }

        return $id;
    }
}
