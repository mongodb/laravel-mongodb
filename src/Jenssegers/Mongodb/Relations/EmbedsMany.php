<?php namespace Jenssegers\Mongodb\Relations;

use MongoId;
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

        if ( ! $model->exists)
        {
            $model->setAttribute('_id', new MongoId);

            $this->updateTimestamps($model);
        }

        if ($siblings->contains($model))
        {
            foreach ($siblings as $i => $sibling)
            {
                if ($sibling->getKey() == $model->getKey())
                {
                    $siblings->put($i, $model);
                    break;
                }
            }
        }
        else
        {
            $siblings->push($model);
        }

        $this->parent->setAttribute($this->localKey, $siblings->toArray());
        //$this->parent->setAttribute($this->localKey, $siblings);

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
