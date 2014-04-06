<?php namespace Jenssegers\Mongodb\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Collection;

use MongoId;

class EmbedsMany extends Relation {

    /**
     * The local key of the parent model.
     *
     * @var string
     */
    protected $localKey;

    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * The "name" of the relationship.
     *
     * @var string
     */
    protected $relation;

    /**
     * Create a new embeds many relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model    $parent
     * @param  string  $localKey
     * @param  string  $foreignKey
     * @param  string  $relation
     * @return void
     */
    public function __construct(Builder $query, Model $parent, Model $related, $localKey, $foreignKey, $relation)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $related;
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;
        $this->relation = $relation;

        $this->addConstraints();
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints)
        {
            $this->query->where($this->parent->getKeyName(), '=', $this->parent->getKey());
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        // There are no eager loading constraints.
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return void
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model)
        {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        foreach ($models as $model)
        {
            $results = $this->getEmbeddedRecords($model);

            $model->setRelation($relation, $this->toCollection($results));
        }

        return $models;
    }

    /**
     * Get the results of the relationship.
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    public function getResults()
    {
        return $this->toCollection($this->getEmbeddedRecords());
    }

    /**
    * Shorthand to get the results of the relationship.
    *
    * @return Illuminate\Database\Eloquent\Collection
    */
    public function get()
    {
        return $this->getResults();
    }

    /**
     * Get the results with given ids.
     *
     * @param  array  $ids
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function find(array $ids)
    {
        $documents = $this->getEmbeddedRecords();

        $primaryKey = $this->related->getKeyName();

        $documents = array_filter($documents, function ($document) use ($primaryKey, $ids)
        {
            return in_array($document[$primaryKey], $ids);
        });

        return $this->toCollection($documents);
    }

    /**
     * Attach a model instance to the parent model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function save(Model $model)
    {
        if ($this->fireModelEvent($model, 'saving') === false) return false;

        $this->updateTimestamps($model);

        // Insert a new document.
        if ( ! $model->exists)
        {
            $result = $this->performInsert($model);
        }

        // Update an existing document.
        else
        {
            $result = $this->performUpdate($model);
        }

        if ($result)
        {
            $this->fireModelEvent($result, 'saved', false);
            return $result;
        }

        return false;
    }

    /**
     * Attach a model instance to the parent model without persistence.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function associate(Model $model)
    {
        // Insert the related model in the parent instance
        if ( ! $model->exists)
        {
            return $this->associateNew($model);
        }

        // Update the related model in the parent instance
        else
        {
            return $this->associateExisting($model);
        }

    }

    /**
     * Perform a model insert operation.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function performInsert(Model $model)
    {
        if ($this->fireModelEvent($model, 'creating') === false) return false;

        // Insert the related model in the parent instance
        $this->associateNew($model);

        // Push the document to the database.
        $result = $this->query->push($this->localKey, $model->getAttributes(), true);

        if ($result)
        {
            $this->fireModelEvent($model, 'created', false);
            return $model;
        }

        return false;
    }

    /**
     * Perform a model update operation.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return Model|bool
     */
    protected function performUpdate(Model $model)
    {
        if ($this->fireModelEvent($model, 'updating') === false) return false;

        // Update the related model in the parent instance
        $this->associateExisting($model);

        // Get the correct foreign key value.
        $id = $this->getForeignKeyValue($model->getKey());

        // Update document in database.
        $result = $this->query->where($this->localKey . '.' . $model->getKeyName(), $id)
                              ->update(array($this->localKey . '.$' => $model->getAttributes()));

        if ($result)
        {
            $this->fireModelEvent($model, 'updated', false);
            return $model;
        }

        return false;
    }

    /**
     * Attach a new model without persistence
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function associateNew($model)
    {
        // Create a new key.
        if ( ! $model->getAttribute('_id'))
        {
            $model->setAttribute('_id', new MongoId);
        }

        $documents = $this->getEmbeddedRecords();

        // Add the document to the parent model.
        $documents[] = $model->getAttributes();

        $this->setEmbeddedRecords($documents);

        // Mark the model as existing.
        $model->exists = true;

        return $model;
    }

    /**
     * Update an existing model without persistence
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function associateExisting($model)
    {
        // Get existing embedded documents.
        $documents = $this->getEmbeddedRecords();

        $primaryKey = $this->related->getKeyName();

        $key = $model->getKey();

        // Replace the document in the parent model.
        foreach ($documents as &$document)
        {
            if ($document[$primaryKey] == $key)
            {
                $document = $model->getAttributes();
                break;
            }
        }

        $this->setEmbeddedRecords($documents);

        return $model;
    }

    /**
     * Attach an array of models to the parent instance.
     *
     * @param  array  $models
     * @return array
     */
    public function saveMany(array $models)
    {
        array_walk($models, array($this, 'save'));

        return $models;
    }

    /**
     * Create a new instance of the related model.
     *
     * @param  array  $attributes
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create(array $attributes)
    {
        // Here we will set the raw attributes to avoid hitting the "fill" method so
        // that we do not have to worry about a mass accessor rules blocking sets
        // on the models. Otherwise, some of these attributes will not get set.
        $instance = $this->related->newInstance();

        $instance->setRawAttributes($attributes);

        return $this->save($instance);
    }

    /**
     * Create an array of new instances of the related model.
     *
     * @param  array  $records
     * @return array
     */
    public function createMany(array $records)
    {
        $instances = array_map(array($this, 'create'), $records);

        return $instances;
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

        $models = $this->find($ids);

        $ids = array();

        $primaryKey = $this->related->getKeyName();

        // Pull the documents from the database.
        foreach ($models as $model)
        {
            if ($this->fireModelEvent($model, 'deleting') === false) continue;

            $id = $model->getKey();
            $this->query->pull($this->localKey, array($primaryKey => $this->getForeignKeyValue($id)));

            $ids[] = $id;

            $this->fireModelEvent($model, 'deleted', false);
        }

        return $this->dissociate($ids);
    }

    /**
     * Dissociate the embedded models for the given IDs without persistence.
     *
     * @param  mixed  $ids
     * @return int
     */
    public function dissociate($ids = array())
    {
        $ids = $this->getIdsArrayFrom($ids);

        $primaryKey = $this->related->getKeyName();

        // Get existing embedded documents.
        $documents = $this->getEmbeddedRecords();

        // Remove the document from the parent model.
        foreach ($documents as $i => $document)
        {
            if (in_array($document[$primaryKey], $ids))
            {
                unset($documents[$i]);
            }
        }

        $this->setEmbeddedRecords($documents);

        // We return the total number of deletes for the operation. The developers
        // can then check this number as a boolean type value or get this total count
        // of records deleted for logging, etc.
        return count($ids);
    }

    /**
     * Transform single ID, single Model or array of Models into an array of IDs
     *
     * @param  mixed  $ids
     * @return array
     */
    protected function getIdsArrayFrom($ids)
    {
        if (! is_array($ids)) $ids = array($ids);

        foreach ($ids as &$id)
        {
            if ($id instanceof Model) $id = $id->getKey();
        }

        return $ids;
    }

    /**
     * Delete alias.
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
     * Convert an array of embedded documents to a Collection.
     *
     * @param  array  $results
     * @return Illuminate\Database\Eloquent\Collection
     */
    protected function toCollection(array $results = array())
    {
        $models = array();

        // Wrap documents in model objects.
        foreach ($results as $model)
        {
            if ( ! $model instanceof Model)
            {
                $model = $this->related->newFromBuilder($model);
            }

            // Attatch the parent relation to the embedded model.
            $model->setRelation($this->foreignKey, $this->parent);
            $model->setHidden(array_merge($model->getHidden(), array($this->foreignKey)));

            $models[] = $model;
        }

        if (count($models) > 0)
        {
            $models = $this->eagerLoadRelations($models);
        }

        return $this->related->newCollection($models);
    }

    /**
     * Get the embedded documents array.
     *
     * @return array
     */
    protected function getEmbeddedRecords(Model $model = null)
    {
        if (is_null($model))
        {
            $model = $this->parent;
        }

        // Get raw attributes to skip relations and accessors.
        $attributes = $model->getAttributes();

        return isset($attributes[$this->localKey]) ? $attributes[$this->localKey] : array();
    }

    /**
     * Set the embedded documents array.
     *
     * @param array $models
     * @return void
     */
    protected function setEmbeddedRecords(array $models)
    {
        $attributes = $this->parent->getAttributes();

        $attributes[$this->localKey] = array_values($models);

        // Set raw attributes to skip mutators.
        $this->parent->setRawAttributes($attributes);

        // Set the relation on the parent.
        $this->parent->setRelation($this->relation, $this->getResults());
    }

    /**
     * Update the creation and update timestamps.
     *
     * @return void
     */
    protected function updateTimestamps(Model $model)
    {
        $time = $model->freshTimestamp();

        if ( ! $model->isDirty(Model::UPDATED_AT))
        {
            $model->setUpdatedAt($time);
        }

        if ( ! $model->exists && ! $model->isDirty(Model::CREATED_AT))
        {
            $model->setCreatedAt($time);
        }
    }

    /**
     * Get the foreign key value for the relation.
     *
     * @param  mixed $id
     * @return mixed
     */
    protected function getForeignKeyValue($id)
    {
        // Convert the id to MongoId if necessary.
        return $this->getBaseQuery()->convertKey($id);
    }

    /**
     * Fire the given event for the given model.
     *
     * @param  string  $event
     * @param  bool    $halt
     * @return mixed
     */
    protected function fireModelEvent(Model $model, $event, $halt = true)
    {
        $dispatcher = $model->getEventDispatcher();

        if ( is_null($dispatcher)) return true;

        // We will append the names of the class to the event to distinguish it from
        // other model events that are fired, allowing us to listen on each model
        // event set individually instead of catching event for all the models.
        $event = "eloquent.{$event}: ".get_class($model);

        $method = $halt ? 'until' : 'fire';

        return $dispatcher->$method($event, $model);
    }

}
