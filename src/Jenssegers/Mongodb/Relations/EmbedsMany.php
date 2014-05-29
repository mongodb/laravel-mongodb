<?php namespace Jenssegers\Mongodb\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Collection;
use MongoId;

class EmbedsMany extends EmbedsOneOrMany {

    /**
     * Get the results of the relationship.
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    public function getResults()
    {
        return $this->toCollection($this->getEmbedded());
    }

    /**
     * Simulate order by method.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return Illuminate\Database\Eloquent\Collection
     */
    public function orderBy($column, $direction = 'asc')
    {
        $descending = strtolower($direction) == 'desc';

        return $this->getResults()->sortBy($column, SORT_REGULAR, $descending);
    }

    /**
     * Associate the model instance to the given parent, without saving it to the database.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function associate(Model $model)
    {
        if ( ! $this->contains($model))
        {
            return $this->associateNew($model);
        }
        else
        {
            return $this->associateExisting($model);
        }
    }

    /**
     * Destroy the embedded models for the given IDs.
     *
     * @param  mixed  $ids
     * @return int
     */
    public function destroy($ids = array())
    {
        $ids = $this->getIdsArrayFrom($ids);

        // Get all models matching the given ids.
        $models = $this->get()->only($ids);

        // Pull the documents from the database.
        foreach ($models as $model)
        {
            $this->performDelete($model);
        }
    }

    /**
     * Dissociate the model instance from the given parent, without saving it to the database.
     *
     * @param  mixed  $ids
     * @return int
     */
    public function dissociate($ids = array())
    {
        $ids = $this->getIdsArrayFrom($ids);

        $records = $this->getEmbedded();
        $primaryKey = $this->related->getKeyName();

        // Remove the document from the parent model.
        foreach ($records as $i => $record)
        {
            if (in_array($record[$primaryKey], $ids))
            {
                unset($records[$i]);
            }
        }

        $this->setEmbedded($records);

        // We return the total number of deletes for the operation. The developers
        // can then check this number as a boolean type value or get this total count
        // of records deleted for logging, etc.
        return count($ids);
    }

    /**
     * Delete all embedded models.
     *
     * @return int
     */
    public function delete()
    {
        // Overwrite the local key with an empty array.
        $result = $this->query->update(array($this->localKey => array()));

        // If the update query was successful, we will remove the embedded records
        // of the parent instance.
        if ($result)
        {
            $count = $this->count();

            $this->setEmbedded(array());

            // Return the number of deleted embedded records.
            return $count;
        }

        return $result;
    }

    /**
     * Destroy alias.
     *
     * @param  mixed  $ids
     * @return int
     */
    public function detach($ids = array())
    {
        return $this->destroy($ids);
    }

    /**
     * Save alias.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function attach(Model $model)
    {
        return $this->save($model);
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

        // Push the new model to the database.
        $result = $this->query->push($this->localKey, $model->getAttributes(), true);

        // Associate the new model to the parent.
        if ($result) $this->associateNew($model);

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
        // Get the correct foreign key value.
        $id = $this->getForeignKeyValue($model);

        // Update document in database.
        $result = $this->query->where($this->localKey . '.' . $model->getKeyName(), $id)
                              ->update(array($this->localKey . '.$' => $model->getAttributes()));

        // Update the related model in the parent instance
        if ($result) $this->associateExisting($model);

        return $result ? $model : false;
    }

    /**
     * Remove an existing model and detach it from the parent model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    protected function performDelete(Model $model)
    {
        if ($this->fireModelEvent($model, 'deleting') === false) return false;

        // Get the correct foreign key value.
        $id = $this->getForeignKeyValue($model);

        $result = $this->query->pull($this->localKey, array($model->getKeyName() => $id));

        if ($result)
        {
            $this->fireModelEvent($model, 'deleted', false);

            // Update the related model in the parent instance
            $this->dissociate($model);

            return true;
        }

        return false;
    }

    /**
     * Associate a new model instance to the given parent, without saving it to the database.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function associateNew($model)
    {
        // Create a new key if needed.
        if ( ! $model->getAttribute('_id'))
        {
            $model->setAttribute('_id', new MongoId);
        }

        $records = $this->getEmbedded();

        // Add the document to the parent model.
        $records[] = $model->getAttributes();

        $this->setEmbedded($records);

        return $model;
    }

    /**
     * Associate an existing model instance to the given parent, without saving it to the database.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function associateExisting($model)
    {
        // Get existing embedded documents.
        $records = $this->getEmbedded();

        $primaryKey = $this->related->getKeyName();
        $key = $model->getKey();

        // Replace the document in the parent model.
        foreach ($records as &$record)
        {
            if ($record[$primaryKey] == $key)
            {
                $record = $model->getAttributes();
                break;
            }
        }

        $this->setEmbedded($records);

        return $model;
    }

    /**
     * Get the embedded records array.
     *
     * @return array
     */
    protected function getEmbedded()
    {
        return parent::getEmbedded() ?: array();
    }

    /**
     * Set the embedded records array.
     *
     * @param array $models
     * @return void
     */
    protected function setEmbedded($models)
    {
        if (! is_array($models)) $models = array($models);

        return parent::setEmbedded(array_values($models));
    }

    /**
     * Handle dynamic method calls to the relationship.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Collection methods
        if (method_exists('Illuminate\Database\Eloquent\Collection', $method))
        {
            return call_user_func_array(array($this->getResults(), $method), $parameters);
        }

        return parent::__call($method, $parameters);
    }

}
