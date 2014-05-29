<?php namespace Jenssegers\Mongodb\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Collection;
use MongoId;

class EmbedsOne extends EmbedsOneOrMany {

    /**
     * Get the results of the relationship.
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    public function getResults()
    {
        return $this->toModel($this->getEmbedded());
    }

    /**
     * Check if a model is already embedded.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function contains($key)
    {
        if ($key instanceof Model) $key = $key->getKey();

        $embedded = $this->getEmbedded();

        $primaryKey = $this->related->getKeyName();

        return ($embedded and $embedded[$primaryKey] == $key);
    }

    /**
     * Associate the model instance to the given parent, without saving it to the database.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function associate(Model $model)
    {
        // Create a new key if needed.
        if ( ! $model->getAttribute('_id'))
        {
            $model->setAttribute('_id', new MongoId);
        }

        $this->setEmbedded($model->getAttributes());

        return $model;
    }

    /**
     * Save a new model and attach it to the parent model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function performInsert(Model $model)
    {
        // Create a new key if needed.
        if ( ! $model->getAttribute('_id'))
        {
            $model->setAttribute('_id', new MongoId);
        }

        $result = $this->query->update(array($this->localKey => $model->getAttributes()));

        if ($result) $this->associate($model);

        return $result ? $model : false;
    }

    /**
     * Save an existing model and attach it to the parent model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return Model|bool
     */
    protected function performUpdate(Model $model)
    {
        $result = $this->query->update(array($this->localKey => $model->getAttributes()));

        if ($result) $this->associate($model);

        return $result ? $model : false;
    }

    /**
     * Delete all embedded models.
     *
     * @return int
     */
    public function delete()
    {
        // Overwrite the local key with an empty array.
        $result = $this->query->update(array($this->localKey => null));

        // If the update query was successful, we will remove the embedded records
        // of the parent instance.
        if ($result)
        {
            $count = $this->count();

            $this->setEmbedded(null);

            // Return the number of deleted embedded records.
            return $count;
        }

        return $result;
    }

}
