<?php namespace Jenssegers\Mongodb\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class EmbedsMany extends EmbedsOneOrMany {

    /**
     * Attach the model to its parent.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function associate(Model $model)
    {
        $siblings = $this->getResults();

        $siblings->push($model);

        $this->parent->setAttribute($this->localKey, $siblings->toArray());

        return $this->parent->setRelation($this->relation, $siblings);
    }

    /**
     * Perform an update on all the related models.
     *
     * @param  array  $attributes
     * @return int
     */
    public function update(array $attributes)
    {
        $siblings = $this->getResults();

        foreach ($siblings as $sibling)
        {
            $sibling->fill($attributes);
        }

        $this->parent->setAttribute($this->localKey, $siblings->toArray());

        return $this->parent->save();
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        $value = array_get($this->parent->getAttributes(), $this->localKey);

        if (is_array($value))
        {
            return $this->related->hydrate($value);
        }
        else
        {
            return $this->related->newCollection();
        }
    }

}
