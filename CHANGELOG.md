# Changelog
All notable changes to this project will be documented in this file.

## [4.9.0] - coming soon

* Add `Connection::getServerVersion()` by @GromNaN in [#3043](https://github.com/mongodb/laravel-mongodb/pull/3043)
* Add `Schema\Builder::getTables()` and `getTableListing` by @GromNaN in [#3044](https://github.com/mongodb/laravel-mongodb/pull/3044)

## [4.6.0] - 2024-07-09

* Add `DocumentModel` trait to use any 3rd party model with MongoDB @GromNaN in [#2580](https://github.com/mongodb/laravel-mongodb/pull/2580)
* Add `HasSchemaVersion` trait to help implementing the [schema versioning pattern](https://www.mongodb.com/docs/manual/tutorial/model-data-for-schema-versioning/) @florianJacques in [#3021](https://github.com/mongodb/laravel-mongodb/pull/3021)
* Add support for Closure for Embed pagination @GromNaN in [#3027](https://github.com/mongodb/laravel-mongodb/pull/3027)

## [4.5.0] - 2024-06-20

* Add GridFS integration for Laravel File Storage by @GromNaN in [#2985](https://github.com/mongodb/laravel-mongodb/pull/2985)

## [4.4.0] - 2024-05-31

* Support collection name prefix by @GromNaN in [#2930](https://github.com/mongodb/laravel-mongodb/pull/2930)
* Ignore `_id: null` to let MongoDB generate an `ObjectId` by @GromNaN in [#2969](https://github.com/mongodb/laravel-mongodb/pull/2969)
* Add `mongodb` driver for Batching by @GromNaN in [#2904](https://github.com/mongodb/laravel-mongodb/pull/2904)
* Rename queue option `table` to `collection`
* Replace queue option `expire` with `retry_after`
* Revert behavior of `createOrFirst` to delegate to `firstOrCreate` when in transaction by @GromNaN in [#2984](https://github.com/mongodb/laravel-mongodb/pull/2984)

## [4.3.1] - 2024-05-31

* Fix memory leak when filling nested fields using dot notation by @GromNaN in [#2962](https://github.com/mongodb/laravel-mongodb/pull/2962)
* Fix PHP error when accessing the connection after disconnect by @SanderMuller in [#2967](https://github.com/mongodb/laravel-mongodb/pull/2967)
* Improve error message for invalid configuration by @GromNaN in [#2975](https://github.com/mongodb/laravel-mongodb/pull/2975)
* Remove `@mixin` annotation from `MongoDB\Laravel\Model` class by @GromNaN in [#2981](https://github.com/mongodb/laravel-mongodb/pull/2981)

## [4.3.0] - 2024-04-26

* New aggregation pipeline builder by @GromNaN in [#2738](https://github.com/mongodb/laravel-mongodb/pull/2738)
* Drop support for Composer 1.x by @GromNaN in [#2784](https://github.com/mongodb/laravel-mongodb/pull/2784)
* Fix `artisan query:retry` command by @GromNaN in [#2838](https://github.com/mongodb/laravel-mongodb/pull/2838)
* Add `mongodb` cache and lock drivers by @GromNaN in [#2877](https://github.com/mongodb/laravel-mongodb/pull/2877)

## [4.2.2] - 2024-04-25

* Add return types to `FindAndModifyCommandSubscriber`, used by `firstOrCreate` by @wivaku in [#2913](https://github.com/mongodb/laravel-mongodb/pull/2913)

## [4.2.1] - 2024-04-25

* Set timestamps when using `Model::createOrFirst()` by @GromNaN in [#2905](https://github.com/mongodb/laravel-mongodb/pull/2905)

## [4.2.0] - 2024-03-14

* Add support for Laravel 11 by @GromNaN in [#2735](https://github.com/mongodb/laravel-mongodb/pull/2735)
* Implement `Model::createOrFirst()` using findOneAndUpdate operation by @GromNaN in [#2742](https://github.com/mongodb/laravel-mongodb/pull/2742)

## [4.1.3] - 2024-03-05

* Fix the timezone of `datetime` fields when they are read from the database. By @GromNaN in [#2739](https://github.com/mongodb/laravel-mongodb/pull/2739)
* Fix support for null values in `datetime` and reset `date` fields with custom format to the start of the day. By @GromNaN in [#2741](https://github.com/mongodb/laravel-mongodb/pull/2741)

## [4.1.2] - 2024-02-22

* Fix support for subqueries using the query builder by @GromNaN in [#2717](https://github.com/mongodb/laravel-mongodb/pull/2717)
* Fix `Query\Builder::dump` and `dd` methods to dump the MongoDB query by @GromNaN in [#2727](https://github.com/mongodb/laravel-mongodb/pull/2727) and [#2730](https://github.com/mongodb/laravel-mongodb/pull/2730)

## [4.1.1] - 2024-01-17

* Fix casting issues by [@stubbo](https://github.com/stubbo) in [#2705](https://github.com/mongodb/laravel-mongodb/pull/2705)
* Move documentation to the mongodb.com domain at [https://www.mongodb.com/docs/drivers/php/laravel-mongodb/current/](https://www.mongodb.com/docs/drivers/php/laravel-mongodb/current/)

## [4.1.0] - 2023-12-14

* PHPORM-100 Support query on numerical field names by [@GromNaN](https://github.com/GromNaN) in [#2642](https://github.com/mongodb/laravel-mongodb/pull/2642)
* Fix casting issue by [@hans-thomas](https://github.com/hans-thomas) in [#2653](https://github.com/mongodb/laravel-mongodb/pull/2653)
* Upgrade minimum Laravel version to 10.30 by [@GromNaN](https://github.com/GromNaN) in [#2665](https://github.com/mongodb/laravel-mongodb/pull/2665)
* Handling single model in sync method by [@hans-thomas](https://github.com/hans-thomas) in [#2648](https://github.com/mongodb/laravel-mongodb/pull/2648)
* BelongsToMany sync does't use configured keys by [@hans-thomas](https://github.com/hans-thomas) in [#2667](https://github.com/mongodb/laravel-mongodb/pull/2667)
* morphTo relationship by [@hans-thomas](https://github.com/hans-thomas) in [#2669](https://github.com/mongodb/laravel-mongodb/pull/2669)
* Datetime casting with custom format by [@hans-thomas](https://github.com/hans-thomas) in [#2658](https://github.com/mongodb/laravel-mongodb/pull/2658)
* PHPORM-106 Implement pagination for groupBy queries by [@GromNaN](https://github.com/GromNaN) in [#2672](https://github.com/mongodb/laravel-mongodb/pull/2672)
* Add method `Connection::ping()` to check server connection by [@hans-thomas](https://github.com/hans-thomas) in [#2677](https://github.com/mongodb/laravel-mongodb/pull/2677)
* PHPORM-119 Fix integration with Spatie Query Builder - Don't qualify field names in document models by [@GromNaN](https://github.com/GromNaN) in [#2676](https://github.com/mongodb/laravel-mongodb/pull/2676)
* Support renaming columns in migrations by [@hans-thomas](https://github.com/hans-thomas) in [#2682](https://github.com/mongodb/laravel-mongodb/pull/2682)
* Add MorphToMany support by [@hans-thomas](https://github.com/hans-thomas) in [#2670](https://github.com/mongodb/laravel-mongodb/pull/2670)
* PHPORM-6 Fix doc Builder::timeout applies to find query, not the cursor by [@GromNaN](https://github.com/GromNaN) in [#2681](https://github.com/mongodb/laravel-mongodb/pull/2681)
* Add test for the `$hidden` property by [@Treggats](https://github.com/Treggats) in [#2687](https://github.com/mongodb/laravel-mongodb/pull/2687)
* Update `push` and `pull` docs by [@hans-thomas](https://github.com/hans-thomas) in [#2685](https://github.com/mongodb/laravel-mongodb/pull/2685)
* Hybrid support for BelongsToMany relationship by [@hans-thomas](https://github.com/hans-thomas) in [#2688](https://github.com/mongodb/laravel-mongodb/pull/2688)
* Avoid unnecessary data fetch for exists method by [@andersonls](https://github.com/andersonls) in [#2692](https://github.com/mongodb/laravel-mongodb/pull/2692)
* Hybrid support for MorphToMany relationship by [@hans-thomas](https://github.com/hans-thomas) in [#2690](https://github.com/mongodb/laravel-mongodb/pull/2690)

## [4.0.3] - 2024-01-17

- Reset `Model::$unset` when a model is saved or refreshed [#2709](https://github.com/mongodb/laravel-mongodb/pull/2709) by [@richardfila](https://github.com/richardfila)

## [4.0.2] - 2023-11-03

- Fix compatibility with Laravel 10.30 [#2661](https://github.com/mongodb/laravel-mongodb/pull/2661) by [@Treggats](https://github.com/Treggats)
- PHPORM-101 Allow empty insert batch for consistency with Eloquent SQL [#2661](https://github.com/mongodb/laravel-mongodb/pull/2645) by [@GromNaN](https://github.com/GromNaN)

*4.0.1 skipped due to a mistake in the release process.*

## [4.0.0] - 2023-09-28

- Rename package to `mongodb/laravel-mongodb`
- Change namespace to `MongoDB\Laravel`
- Add classes to cast `ObjectId` and `UUID` instances [5105553](https://github.com/mongodb/laravel-mongodb/commit/5105553cbb672a982ccfeaa5b653d33aaca1553e) by [@alcaeus](https://github.com/alcaeus).
- Add `Query\Builder::toMql()` to simplify comprehensive query tests [ae3e0d5](https://github.com/mongodb/laravel-mongodb/commit/ae3e0d5f72c24edcb2a78d321910397f4134e90f) by @GromNaN.
- Fix `Query\Builder::whereNot` to use MongoDB [`$not`](https://www.mongodb.com/docs/manual/reference/operator/query/not/) operator [e045fab](https://github.com/mongodb/laravel-mongodb/commit/e045fab6c315fe6d17f75669665898ed98b88107) by @GromNaN.
- Fix `Query\Builder::whereBetween` to accept `Carbon\Period` object [f729baa](https://github.com/mongodb/laravel-mongodb/commit/f729baad59b4baf3307121df7f60c5cd03a504f5) by @GromNaN.
- Throw an exception for unsupported `Query\Builder` methods [e1a83f4](https://github.com/mongodb/laravel-mongodb/commit/e1a83f47f16054286bc433fc9ccfee078bb40741) by @GromNaN.
- Throw an exception when `Query\Builder::orderBy()` is used with invalid direction [edd0871](https://github.com/mongodb/laravel-mongodb/commit/edd08715a0dd64bab9fd1194e70fface09e02900) by @GromNaN.
- Throw an exception when `Query\Builder::push()` is used incorrectly [19cf7a2](https://github.com/mongodb/laravel-mongodb/commit/19cf7a2ee2c0f2c69459952c4207ee8279b818d3) by @GromNaN.
- Remove public property `Query\Builder::$paginating` [e045fab](https://github.com/mongodb/laravel-mongodb/commit/e045fab6c315fe6d17f75669665898ed98b88107) by @GromNaN.
- Remove call to deprecated `Collection::count` for `countDocuments` [4514964](https://github.com/mongodb/laravel-mongodb/commit/4514964145c70c37e6221be8823f8f73a201c259) by @GromNaN.
- Accept operators prefixed by `$` in `Query\Builder::orWhere` [0fb83af](https://github.com/mongodb/laravel-mongodb/commit/0fb83af01284cb16def1eda6987432ebbd64bb8f) by @GromNaN.
- Remove `Query\Builder::whereAll($column, $values)`. Use `Query\Builder::where($column, 'all', $values)` instead. [1d74dc3](https://github.com/mongodb/laravel-mongodb/commit/1d74dc3d3df9f7a579b343f3109160762050ca01) by @GromNaN.
- Fix validation of unique values when the validated value is found as part of an existing value. [d5f1bb9](https://github.com/mongodb/laravel-mongodb/commit/d5f1bb901f3e3c6777bc604be1af0a8238dc089a) by @GromNaN.
- Support `%` and `_` in `like` expression [ea89e86](https://github.com/mongodb/laravel-mongodb/commit/ea89e8631350cd81c8d5bf977efb4c09e60d7807) by @GromNaN.
- Change signature of `Query\Builder::__constructor` to match the parent class [#2570](https://github.com/mongodb/laravel-mongodb/pull/2570) by @GromNaN.
- Fix Query on `whereDate`, `whereDay`, `whereMonth`, `whereYear`, `whereTime` to use MongoDB operators [#2376](https://github.com/mongodb/laravel-mongodb/pull/2376) by [@Davpyu](https://github.com/Davpyu) and @GromNaN.
- `Model::unset()` does not persist the change. Call `Model::save()` to persist the change [#2578](https://github.com/mongodb/laravel-mongodb/pull/2578) by @GromNaN.
- Support delete one document with `Query\Builder::limit(1)->delete()` [#2591](https://github.com/mongodb/laravel-mongodb/pull/2591) by @GromNaN
- Add trait `MongoDB\Laravel\Eloquent\MassPrunable` to replace the Eloquent trait on MongoDB models [#2598](https://github.com/mongodb/laravel-mongodb/pull/2598) by @GromNaN

## [3.9.2] - 2022-09-01

### Added
- Add single word name mutators [#2438](https://github.com/mongodb/laravel-mongodb/pull/2438) by [@RosemaryOrchard](https://github.com/RosemaryOrchard) & [@mrneatly](https://github.com/mrneatly).

### Fixed
- Fix stringable sort [#2420](https://github.com/mongodb/laravel-mongodb/pull/2420) by [@apeisa](https://github.com/apeisa).

## [3.9.1] - 2022-03-11

### Added
- Backport support for cursor pagination [#2358](https://github.com/mongodb/laravel-mongodb/pull/2358) by [@Jeroenwv](https://github.com/Jeroenwv).

### Fixed
- Check if queue service is disabled [#2357](https://github.com/mongodb/laravel-mongodb/pull/2357) by [@robjbrain](https://github.com/robjbrain).

## [3.9.0] - 2022-02-17

### Added
- Compatibility with Laravel 9.x [#2344](https://github.com/mongodb/laravel-mongodb/pull/2344) by [@divine](https://github.com/divine).

## [3.8.4] - 2021-05-27

### Fixed
- Fix getRelationQuery breaking changes [#2263](https://github.com/mongodb/laravel-mongodb/pull/2263) by [@divine](https://github.com/divine).
- Apply fixes produced by php-cs-fixer [#2250](https://github.com/mongodb/laravel-mongodb/pull/2250) by [@divine](https://github.com/divine).

### Changed
- Add doesntExist to passthru [#2194](https://github.com/mongodb/laravel-mongodb/pull/2194) by [@simonschaufi](https://github.com/simonschaufi).
- Add Model query whereDate support [#2251](https://github.com/mongodb/laravel-mongodb/pull/2251) by [@yexk](https://github.com/yexk).
- Add transaction free deleteAndRelease() method [#2229](https://github.com/mongodb/laravel-mongodb/pull/2229) by [@sodoardi](https://github.com/sodoardi).
- Add setDatabase to Jenssegers\Mongodb\Connection [#2236](https://github.com/mongodb/laravel-mongodb/pull/2236) by [@ThomasWestrelin](https://github.com/ThomasWestrelin).
- Check dates against DateTimeInterface instead of DateTime [#2239](https://github.com/mongodb/laravel-mongodb/pull/2239) by [@jeromegamez](https://github.com/jeromegamez).
- Move from psr-0 to psr-4 [#2247](https://github.com/mongodb/laravel-mongodb/pull/2247) by [@divine](https://github.com/divine).

## [3.8.3] - 2021-02-21

### Changed
- Fix query builder regression [#2204](https://github.com/mongodb/laravel-mongodb/pull/2204) by [@divine](https://github.com/divine).

## [3.8.2] - 2020-12-18

### Changed
- MongodbQueueServiceProvider does not use the DB Facade anymore [#2149](https://github.com/mongodb/laravel-mongodb/pull/2149) by [@curosmj](https://github.com/curosmj).
- Add escape regex chars to DB Presence Verifier [#1992](https://github.com/mongodb/laravel-mongodb/pull/1992) by [@andrei-gafton-rtgt](https://github.com/andrei-gafton-rtgt).

## [3.8.1] - 2020-10-23

### Added
- Laravel 8 support by [@divine](https://github.com/divine).

### Changed
- Fix like with numeric values [#2127](https://github.com/mongodb/laravel-mongodb/pull/2127) by [@hnassr](https://github.com/hnassr).

## [3.8.0] - 2020-09-03

### Added
- Laravel 8 support & updated versions of all dependencies [#2108](https://github.com/mongodb/laravel-mongodb/pull/2108) by [@divine](https://github.com/divine).
