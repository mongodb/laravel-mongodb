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
    public function __construct(Builder $query, Model $parent, $localKey, $foreignKey, $relation)
    {
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;
        $this->relation = $relation;

        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
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
        return $models;
    }

    /**
     * Get the results of the relationship.
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    public function getResults()
    {
        // Get embedded documents.
        $results = $this->getEmbedded();

        $models = array();

        // Wrap documents in model objects.
        foreach ($results as $result)
        {
            $model = $this->related->newFromBuilder($result);

            // Attatch the parent relation to the embedded model.
            $model->setRelation($this->foreignKey, $this->parent);

            $models[] = $model;
        }

        return $this->related->newCollection($models);
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
        if (!$model->exists)
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
        if (!isset($model['_id']))
        {
            $model->setAttribute('_id', new MongoId);
        }

        // Set timestamps.
        if ($model->usesTimestamps())
        {
            $time = $model->freshTimestamp();

            $model->setUpdatedAt($time);
            $model->setCreatedAt($time);
        }

        $model->exists = true;

        // Push the document to the database.
        $result = $this->parent->push($this->localKey, $model->getAttributes(), true);

        // Get existing embedded documents.
        $documents = $this->getEmbedded();

        // Add the document to the parent model.
        $documents[] = $model->getAttributes();

        $this->setEmbedded($documents);

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
        if ($model->usesTimestamps())
        {
            $time = $model->freshTimestamp();

            $model->setUpdatedAt($time);
        }

        $key = $model->getKey();

        $primaryKey = $model->getKeyName();

        // Get existing embedded documents.
        $documents = $this->getEmbedded();

        // Replace the document in the parent model.
        foreach ($documents as $i => $document)
        {
            if ($document[$primaryKey] == $key)
            {
                $documents[$i] = $model->getAttributes();
                break;
            }
        }

        $this->setEmbedded($documents);

        return $this->parent->save() ? $model : false;
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
        $instances = array();

        foreach ($records as $record)
        {
            $instances[] = $this->create($record);
        }

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

        // Get existing embedded documents.
        $documents = $this->getEmbedded();

        $primaryKey = $this->related->getKeyName();

        // Remove the document from the parent model.
        foreach ($documents as $i => $document)
        {
            if (in_array($document[$primaryKey], $ids))
            {
                unset($documents[$i]);
                $count++;
            }
        }

        $this->setEmbedded($documents);

        $this->parent->save();

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
     * Get the embedded documents array
     *
     * @return array
     */
    public function getEmbedded()
    {
        // Get raw attributes to skip relations and accessors.
        $attributes = $this->parent->getAttributes();

        return isset($attributes[$this->localKey]) ? $attributes[$this->localKey] : array();
    }

    /**
     * Set the embedded documents array
     *
     * @param array $models
     */
    public function setEmbedded(array $models)
    {
        $attributes = $this->parent->getAttributes();

        $attributes[$this->localKey] = array_values($models);

        // Set raw attributes to skip mutators.
        $this->parent->setRawAttributes($attributes);

        // Set the relation on the parent.
        $this->parent->setRelation($this->relation, $this->getResults());
    }

}
