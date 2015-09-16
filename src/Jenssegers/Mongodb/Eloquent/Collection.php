<?php namespace Jenssegers\Mongodb\Eloquent;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class Collection extends EloquentCollection {

    /**
     * Simulate a get clause on the collection.
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key = null, $default = null)
    {
        if (is_null($key) and is_null($default))
        {
            return $this;
        }

        return parent::get($key, $default);
    }

    /**
     * Simulate a basic where clause on the collection.
     *
     * @param  string  $key
     * @param  string  $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return $this
     */
    public function where($key, $operator = null, $value = null)
    {
        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going.
        if (func_num_args() == 2)
        {
            list($value, $operator) = [$operator, '='];
        }

        return $this->filter(function ($item) use ($key, $operator, $value)
        {
            $actual = $item->{$key};

            switch ($operator)
            {
                case '<>':
                case '!=':
                    return $actual != $value;
                    break;

                case '>':
                    return $actual > $value;
                    break;

                case '<':
                    return $actual < $value;
                    break;

                case '>=':
                    return $actual >= $value;
                    break;

                case '<=':
                    return $actual <= $value;
                    break;

                case 'between':
                    return $actual >= $value[0] and $actual <= $value[1];
                    break;

                case 'not between':
                    return $actual < $value[0] or $actual > $value[1];
                    break;

                case 'in':
                    return in_array($actual, $value);
                    break;

                case 'not in':
                    return ! in_array($actual, $value);
                    break;

                case '=':
                default:
                    return $actual == $value;
                    break;
            }
        });
    }

    /**
     * Add a where between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereBetween($column, array $values, $boolean = 'and', $not = false)
    {
        $type = $not ? 'not between' : 'between';

        return $this->where($column, $type, $values);
    }

    /**
     * Add a where not between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @return $this
     */
    public function whereNotBetween($column, array $values, $boolean = 'and')
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @param  string  $boolean
     * @param  bool    $not
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $type = $not ? 'not in' : 'in';

        return $this->where($column, $type, $values);
    }

    /**
     * Add a "where not in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @param  string  $boolean
     * @return $this
     */
    public function whereNotIn($column, $values, $boolean = 'and')
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Add a "where null" clause to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @param  bool    $not
     * @return $this
     */
    public function whereNull($column, $boolean = 'and', $not = false)
    {
        return $this->where($column, '=', null);
    }

    /**
     * Add a "where not null" clause to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @return $this
     */
    public function whereNotNull($column, $boolean = 'and')
    {
        return $this->where($column, '!=', null);
    }

    /**
     * Simulate order by clause on the collection.
     *
     * @param  string  $key
     * @param  string  $direction
     * @return $this
     */
    public function orderBy($key, $direction = 'asc')
    {
        $descending = strtolower($direction) == 'desc';

        return $this->sortBy($key, SORT_REGULAR, $descending);
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function latest($column = 'created_at')
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function oldest($column = 'created_at')
    {
        return $this->orderBy($column, 'asc');
    }

    /**
     * Set the "offset" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function offset($value)
    {
        $offset = max(0, $value);

        return $this->slice($offset);
    }

    /**
     * Alias to set the "offset" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function skip($value)
    {
        return $this->offset($value);
    }

    /**
     * Set the "limit" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function limit($value)
    {
        return $this->take($value);
    }

}
