<?php namespace Jenssegers\Mongodb\Eloquent;

use Illuminate\Database\Eloquent\Collection as BaseCollection;

class Collection extends BaseCollection {

    /**
     * Simulate a basic where clause on the ollection.
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
            list($value, $operator) = array($operator, '=');
        }

        return $this->filter(function($item) use ($key, $operator, $value)
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

                case 'between':
                    return $actual >= $value[0] and $actual <= $value[1];
                    break;

                case '=':
                default:
                    return $actual == $value;
                    break;
            }
        });
    }

    /**
     * Simulate order by.
     *
     * @param  string  $key
     * @param  string  $direction
     * @return Illuminate\Database\Eloquent\Collection
     */
    public function orderBy($key, $direction = 'asc')
    {
        $descending = strtolower($direction) == 'desc';

        return $this->sortBy($key, SORT_REGULAR, $descending);
    }

}
