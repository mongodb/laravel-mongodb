Upgrading
=========

The PHP library uses [semantic versioning](https://semver.org/). Upgrading to a new major version may require changes to your application.

Upgrading from version 3 to 4
-----------------------------

- Laravel 10.x is required
- Change dependency name in your composer.json to `"mongodb/laravel-mongodb": "^4.0"` and run `composer update`
- Change namespace from `Jenssegers\Mongodb\` to `MongoDB\Laravel\` in your models and config
- Remove support for non-Laravel projects
- Replace `$dates` with `$casts` in your models
- Call `$model->save()` after `$model->unset('field')` to persist the change
- Replace calls to `Query\Builder::whereAll($column, $values)` with `Query\Builder::where($column, 'all', $values)`
- `Query\Builder::delete()` doesn't accept `limit()` other than `1` or `null`.
- `whereDate`, `whereDay`, `whereMonth`, `whereYear`, `whereTime` now use MongoDB operators on date fields
- Replace `Illuminate\Database\Eloquent\MassPrunable` with `MongoDB\Laravel\Eloquent\MassPrunable` in your models
- Remove calls to not-supported methods of `Query\Builder`: `toSql`, `toRawSql`, `whereColumn`, `whereFullText`, `groupByRaw`, `orderByRaw`, `unionAll`, `union`, `having`, `havingRaw`, `havingBetween`, `whereIntegerInRaw`, `orWhereIntegerInRaw`, `whereIntegerNotInRaw`, `orWhereIntegerNotInRaw`.
