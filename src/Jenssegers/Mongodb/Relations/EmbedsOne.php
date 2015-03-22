<?php namespace Jenssegers\Mongodb\Relations;

use MongoId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EmbedsOne extends EmbedsOneOrMany {

    /**
     * Attach the model to its parent.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function associate(Model $model)
    {
        if ( ! $model->exists)
        {
            $model->setAttribute('_id', new MongoId);

            $this->updateTimestamps($model);
        }

        $this->parent->setAttribute($this->localKey, $model->getAttributes());
        //$this->parent->setAttribute($this->localKey, $model);

        return $this->parent->setRelation($this->relation, $model);
    }

    /**
     * Perform an update on all the related models.
     *
     * @param  array  $attributes
     * @return int
     */
    public function update(array $attributes)
    {
        $related = $this->getResults();

        $related->fill($attributes);

        $this->associate($related);

        return $this->parent->save();
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        $attributes = $this->parent->getAttributes();

        $value = array_get($attributes, $this->localKey);

        if ( ! is_null($value))
        {
            return $this->related->newFromBuilder((array) $value);
        }
    }

}
