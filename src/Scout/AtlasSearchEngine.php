<?php

namespace MongoDB\Laravel\Scout;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use MongoDB\Driver\Exception\ServerException;
use MongoDB\Exception\Exception;
use MongoDB\Laravel\Connection;

use function array_filter;
use function array_flip;
use function array_merge;
use function call_user_func;
use function class_uses_recursive;
use function collect;
use function count;
use function in_array;
use function substr;

final class AtlasSearchEngine extends Engine
{
    public function __construct(
        private Connection $mongodb,
        private bool $softDelete = true,
    ) {
    }

    /**
     * Update the given model in the index.
     *
     * @param  Collection $models
     *
     * @return void
     *
     * @throws Exception
     */
    public function update($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $collection = $this->mongodb->getCollection($models->first()->indexableAs());

        if ($this->usesSoftDelete($models->first()) && $this->softDelete) {
            $models->each->pushSoftDeleteMetadata();
        }

        $bulk = [];
        foreach ($models as $model) {
            $searchableData = $model->toSearchableArray();

            if ($searchableData) {
                unset($searchableData['_id']);

                $bulk[] = [
                    'updateOne' => [
                        ['_id' => $model->getScoutKey()],
                        [
                            '$set' => array_merge(
                                $searchableData,
                                $model->scoutMetadata(),
                            ),
                            '$setOnInsert' => ['_id' => $model->getScoutKey()],
                        ],
                        ['upsert' => true],
                    ],
                ];
            }
        }

        if (! empty($bulk)) {
            $collection->bulkWrite($bulk);
        }
    }

    /**
     * Remove the given model from the index.
     *
     * @param  Collection $models
     *
     * @return void
     */
    public function delete($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $collection = $this->mongodb->getCollection($models->first()->indexableAs());

        $bulk = [];
        foreach ($models as $model) {
            $bulk[] = [
                'deleteOne' => [
                    ['_id' => $model->getScoutKey()],
                ],
            ];
        }

        if (! empty($bulk)) {
            $collection->bulkWrite($bulk);
        }
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder $builder
     *
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'numericFilters' => $this->filters($builder),
            'hitsPerPage' => $builder->limit,
        ]));
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder $builder
     * @param  int     $perPage
     * @param  int     $page
     *
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, [
            'numericFilters' => $this->filters($builder),
            'hitsPerPage' => $perPage,
            'page' => $page - 1,
        ]);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder $builder
     * @param  array   $options
     *
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $algolia = $this->algolia->initIndex(
            $builder->index ?: $builder->model->searchableAs(),
        );

        $options = array_merge($builder->options, $options);

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $algolia,
                $builder->query,
                $options,
            );
        }

        return $algolia->search($builder->query, $options);
    }

    /**
     * Get the filter array for the query.
     *
     * @param  Builder $builder
     *
     * @return array
     */
    protected function filters(Builder $builder)
    {
        $wheres = collect($builder->wheres)->map(function ($value, $key) {
            return $key . '=' . $value;
        })->values();

        return $wheres->merge(collect($builder->whereIns)->map(function ($values, $key) {
            if (empty($values)) {
                return '0=1';
            }

            return collect($values)->map(function ($value) use ($key) {
                return $key . '=' . $value;
            })->all();
        })->values())->values()->all();
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed $results
     *
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return collect($results)->pluck('_id')->values();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  Builder $builder
     * @param  mixed   $results
     * @param  Model   $model
     *
     * @return Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        if (count($results) === 0) {
            return $model->newCollection();
        }

        $objectIds = collect($results)->pluck('_id')->values()->all();

        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds(
            $builder,
            $objectIds,
        )->filter(function ($model) use ($objectIds) {
            return in_array($model->getScoutKey(), $objectIds);
        })->map(function ($model) use ($results, $objectIdPositions) {
            $result = $results['hits'][$objectIdPositions[$model->getScoutKey()]] ?? [];

            foreach ($result as $key => $value) {
                if ($key !== '_id' && $key[0] ?? '' === '_') {
                    $model->withScoutMetadata($key, $value);
                }
            }

            return $model;
        })->sortBy(function ($model) use ($objectIdPositions) {
            return $objectIdPositions[$model->getScoutKey()];
        })->values();
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param  Builder $builder
     * @param  mixed   $results
     * @param  Model   $model
     *
     * @return LazyCollection
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        if (count($results['hits']) === 0) {
            return LazyCollection::make($model->newCollection());
        }

        $objectIds = collect($results['hits'])->pluck('objectID')->values()->all();
        $objectIdPositions = array_flip($objectIds);

        return $model->queryScoutModelsByIds(
            $builder,
            $objectIds,
        )->cursor()->filter(function ($model) use ($objectIds) {
            return in_array($model->getScoutKey(), $objectIds);
        })->map(function ($model) use ($results, $objectIdPositions) {
            $result = $results['hits'][$objectIdPositions[$model->getScoutKey()]] ?? [];

            foreach ($result as $key => $value) {
                if (substr($key, 0, 1) === '_') {
                    $model->withScoutMetadata($key, $value);
                }
            }

            return $model;
        })->sortBy(function ($model) use ($objectIdPositions) {
            return $objectIdPositions[$model->getScoutKey()];
        })->values();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed $results
     *
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['nbHits'];
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  Model $model
     *
     * @return void
     */
    public function flush($model)
    {
        $this->mongodb->getCollection($model->indexableAs())->deleteMany([]);
    }

    /**
     * Create a search index.
     *
     * @param  string $name
     * @param  array  $options
     *
     * @return mixed
     *
     * @throws ServerException
     */
    public function createIndex($name, array $options = [])
    {
        $this->mongodb->getMongoDB()->createCollection($name);
        $this->mongodb->getCollection($name)->createSearchIndex([
            'name' => 'laravel_scout',
        ]);
    }

    /**
     * Delete a search index.
     *
     * @param  string $name
     *
     * @return mixed
     */
    public function deleteIndex($name)
    {
        return $this->mongodb->getCollection($name)->drop();
    }

    /**
     * Determine if the given model uses soft deletes.
     *
     * @param  Model $model
     *
     * @return bool
     */
    protected function usesSoftDelete($model)
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }

    private function getMongoDBCollections(Collection $models): \MongoDB\Laravel\Collection
    {
        return $this->mongodb->getCollection($models->first()->indexableAs());
    }
}
