<?php namespace Jenssegers\Eloquent;

class Builder extends \Illuminate\Database\Eloquent\Builder {

    /**
     * The methods that should be returned from query builder.
     *
     * @var array
     */
    protected $passthru = array(
        'toSql', 'lists', 'insert', 'insertGetId', 'pluck',
        'count', 'min', 'max', 'avg', 'sum', 'exists', 'push', 'pull'
    );

}