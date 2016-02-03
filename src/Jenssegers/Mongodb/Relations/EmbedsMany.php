<?php namespace Jenssegers\Mongodb\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use MongoId;

class EmbedsMany extends EmbedsOneOrMany
{
    /**
     * Get the results of the relationship.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getResults()
    {
        return $this->toCollection($this->getEmbedded());
    }

    /**
     * Save a new model and attach it to the parent model.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function performInsert(Model $model)
    {
        // Generate a new key if needed.
        if ($model->getKeyName() == '_id' and ! $model->getKey()) {
            $model->setAttribute('_id', new MongoId);
        }

        // For deeply nested documents, let the parent handle the changes.
        if ($this->isNested()) {
            $this->associate($model);

            return $this->parent->save();
        }

        // Push the new model to the database.
        $result = $this->getBaseQuery()->push($this->localKey, $model->getAttributes(), true);

        // Attach the model to its parent.
        if ($result) {
            $this->associate($model);
        }

        return $result ? $model : false;
    }

    /**
     * Save an existing model and attach it to the parent model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return Model|bool
     */
    public function performUpdate(Model $model)
    {
        // For deeply nested documents, let the parent handle the changes.
        if ($this->isNested()) {
            $this->associate($model);

            return $this->parent->save();
        }

        // Get the correct foreign key value.
        $foreignKey = $this->getForeignKeyValue($model);

        // Use array dot notation for better update behavior.
        $values = array_dot($model->getDirty(), $this->localKey . '.$.');

        // Update document in database.
        $result = $this->getBaseQuery()->where($this->localKey . '.' . $model->getKeyName(), $foreignKey)
                                       ->update($values);

        // Attach the model to its parent.
        if ($result) {
            $this->associate($model);
        }

        return $result ? $model : false;
    }

    /**
     * Delete an existing model and detach it from the parent model.
     *
     * @param  Model  $model
     * @return int
     */
    public function performDelete(Model $model)
    {
        // For deeply nested documents, let the parent handle the changes.
        if ($this->isNested()) {
            $this->dissociate($model);

            return $this->parent->save();
        }

        // Get the correct foreign key value.
        $foreignKey = $this->getForeignKeyValue($model);

        $result = $this->getBaseQuery()->pull($this->localKey, [$model->getKeyName() => $foreignKey]);

        if ($result) {
            $this->dissociate($model);
        }

        return $result;
    }

    /**
     * Associate the model instance to the given parent, without saving it to the database.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function associate(Model $model)
    {
        if (! $this->contains($model)) {
            return $this->associateNew($model);
        } else {
            return $this->associateExisting($model);
        }
    }

    /**
     * Dissociate the model instance from the given parent, without saving it to the database.
     *
     * @param  mixed  $ids
     * @return int
     */
    public function dissociate($ids = [])
    {
        $ids = $this->getIdsArrayFrom($ids);

        $records = $this->getEmbedded();

        $primaryKey = $this->related->getKeyName();

        // Remove the document from the parent model.
        foreach ($records as $i => $record) {
            if (in_array($record[$primaryKey], $ids)) {
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
     * Destroy the embedded models for the given IDs.
     *
     * @param  mixed  $ids
     * @return int
     */
    public function destroy($ids = [])
    {
        $count = 0;

        $ids = $this->getIdsArrayFrom($ids);

        // Get all models matching the given ids.
        $models = $this->getResults()->only($ids);

        // Pull the documents from the database.
        foreach ($models as $model) {
            if ($model->delete()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Delete all embedded models.
     *
     * @return int
     */
    public function delete()
    {
        // Overwrite the local key with an empty array.
        $result = $this->query->update([$this->localKey => []]);

        if ($result) {
            $this->setEmbedded([]);
        }

        return $result;
    }

    /**
     * Destroy alias.
     *
     * @param  mixed  $ids
     * @return int
     */
    public function detach($ids = [])
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
     * Associate a new model instance to the given parent, without saving it to the database.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function associateNew($model)
    {
        // Create a new key if needed.
        if (! $model->getAttribute('_id')) {
            $model->setAttribute('_id', new MongoId);
        }

        $records = $this->getEmbedded();

        // Add the new model to the embedded documents.
        $records[] = $model->getAttributes();

        return $this->setEmbedded($records);
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
        foreach ($records as &$record) {
            if ($record[$primaryKey] == $key) {
                $record = $model->getAttributes();
                break;
            }
        }

        return $this->setEmbedded($records);
    }

    /**
     * Get a paginator for the "select" statement.
     *
     * @param  int  $perPage
     * @return \Illuminate\Pagination\Paginator
     */
    public function paginate($perPage = null)
    {
        $page = Paginator::resolveCurrentPage();
        $perPage = $perPage ?: $this->related->getPerPage();

        $results = $this->getEmbedded();

        $total = count($results);

        $start = ($page - 1) * $perPage;
        $sliced = array_slice($results, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
        ]);
    }

    /**
     * Get the embedded records array.
     *
     * @return array
     */
    protected function getEmbedded()
    {
        return parent::getEmbedded() ?: [];
    }

    /**
     * Set the embedded records array.
     *
     * @param  array  $models
     */
    protected function setEmbedded($models)
    {
        if (! is_array($models)) {
            $models = [$models];
        }

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
        if (method_exists('Illuminate\Database\Eloquent\Collection', $method)) {
            return call_user_func_array([$this->getResults(), $method], $parameters);
        }

        return parent::__call($method, $parameters);
    }
}
