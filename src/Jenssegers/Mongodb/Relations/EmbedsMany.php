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
     * Attach a model instance to the parent model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function save(Model $model)
    {
        // Insert a new document.
        if ( ! $model->exists)
        {
            return $this->performInsert($model);
        }

        // Update an existing document.
        else
        {
            return $this->performUpdate($model);
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
        // Create a new key.
        if ( ! $model->getAttribute('_id'))
        {
            $model->setAttribute('_id', new MongoId);
        }

        // Update timestamps.
        $this->updateTimestamps($model);

        // Push the document to the database.
        $result = $this->query->push($this->localKey, $model->getAttributes(), true);

        $documents = $this->getEmbeddedRecords();

        // Add the document to the parent model.
        $documents[] = $model->getAttributes();

        $this->setEmbeddedRecords($documents);

        // Mark the model as existing.
        $model->exists = true;

        return $result ? $model : false;
    }

    /**
     * Perform a model update operation.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    protected function performUpdate(Model $model)
    {
        // Update timestamps.
        $this->updateTimestamps($model);

        // Get the correct foreign key value.
        $id = $this->getForeignKeyValue($model->getKey());

        // Update document in database.
        $result = $this->query->where($this->localKey . '.' . $model->getKeyName(), $id)
                              ->update(array($this->localKey . '.$' => $model->getAttributes()));

        // Get existing embedded documents.
        $documents = $this->getEmbeddedRecords();

        $primaryKey = $this->related->getKeyName();

        $key = $model->getKey();

        // Replace the document in the parent model.
        foreach ($documents as $i => $document)
        {
            if ($document[$primaryKey] == $key)
            {
                $documents[$i] = $model->getAttributes();
                break;
            }
        }

        $this->setEmbeddedRecords($documents);

        return $result ? $model : false;
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
     * @param  array|int  $ids
     * @return int
     */
    public function destroy($ids = array())
    {
        // We'll initialize a count here so we will return the total number of deletes
        // for the operation. The developers can then check this number as a boolean
        // type value or get this total count of records deleted for logging, etc.
        $count = 0;

        if ($ids instanceof Model) $ids = (array) $ids->getKey();

        // If associated IDs were passed to the method we will only delete those
        // associations, otherwise all of the association ties will be broken.
        // We'll return the numbers of affected rows when we do the deletes.
        $ids = (array) $ids;

        $primaryKey = $this->related->getKeyName();

        // Pull the documents from the database.
        foreach ($ids as $id)
        {
            $this->query->pull($this->localKey, array($primaryKey => $this->getForeignKeyValue($id)));
            $count++;
        }

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

        return $count;
    }

    /**
     * Delete alias.
     *
     * @param  int|array  $ids
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

}
