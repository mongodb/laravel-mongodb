# Changelog
All notable changes to this project will be documented in this file.

## [Unreleased]

## [3.9.2] - 2022-09-01

### Addded 
- Add single word name mutators [#2438](https://github.com/jenssegers/laravel-mongodb/pull/2438) by [@RosemaryOrchard](https://github.com/RosemaryOrchard) & [@mrneatly](https://github.com/mrneatly).

### Fixed
- Fix stringable sort [#2420](https://github.com/jenssegers/laravel-mongodb/pull/2420) by [@apeisa](https://github.com/apeisa).

## [3.9.1] - 2022-03-11

### Added
- Backport support for cursor pagination [#2358](https://github.com/jenssegers/laravel-mongodb/pull/2358) by [@Jeroenwv](https://github.com/Jeroenwv).

### Fixed
- Check if queue service is disabled [#2357](https://github.com/jenssegers/laravel-mongodb/pull/2357) by [@robjbrain](https://github.com/robjbrain).

## [3.9.0] - 2022-02-17

### Added
- Compatibility with Laravel 9.x [#2344](https://github.com/jenssegers/laravel-mongodb/pull/2344) by [@divine](https://github.com/divine).

## [3.8.4] - 2021-05-27

### Fixed
- Fix getRelationQuery breaking changes [#2263](https://github.com/jenssegers/laravel-mongodb/pull/2263) by [@divine](https://github.com/divine).
- Apply fixes produced by php-cs-fixer [#2250](https://github.com/jenssegers/laravel-mongodb/pull/2250) by [@divine](https://github.com/divine).

### Changed
- Add doesntExist to passthru [#2194](https://github.com/jenssegers/laravel-mongodb/pull/2194) by [@simonschaufi](https://github.com/simonschaufi).
- Add Model query whereDate support [#2251](https://github.com/jenssegers/laravel-mongodb/pull/2251) by [@yexk](https://github.com/yexk).
- Add transaction free deleteAndRelease() method [#2229](https://github.com/jenssegers/laravel-mongodb/pull/2229) by [@sodoardi](https://github.com/sodoardi).
- Add setDatabase to Jenssegers\Mongodb\Connection [#2236](https://github.com/jenssegers/laravel-mongodb/pull/2236) by [@ThomasWestrelin](https://github.com/ThomasWestrelin).
- Check dates against DateTimeInterface instead of DateTime [#2239](https://github.com/jenssegers/laravel-mongodb/pull/2239) by [@jeromegamez](https://github.com/jeromegamez).
- Move from psr-0 to psr-4 [#2247](https://github.com/jenssegers/laravel-mongodb/pull/2247) by [@divine](https://github.com/divine).

## [3.8.3] - 2021-02-21

### Changed
- Fix query builder regression [#2204](https://github.com/jenssegers/laravel-mongodb/pull/2204) by [@divine](https://github.com/divine).

## [3.8.2] - 2020-12-18

### Changed
- MongodbQueueServiceProvider does not use the DB Facade anymore [#2149](https://github.com/jenssegers/laravel-mongodb/pull/2149) by [@curosmj](https://github.com/curosmj).
- Add escape regex chars to DB Presence Verifier [#1992](https://github.com/jenssegers/laravel-mongodb/pull/1992) by [@andrei-gafton-rtgt](https://github.com/andrei-gafton-rtgt).

## [3.8.1] - 2020-10-23

### Added
- Laravel 8 support by [@divine](https://github.com/divine).

### Changed
- Fix like with numeric values [#2127](https://github.com/jenssegers/laravel-mongodb/pull/2127) by [@hnassr](https://github.com/hnassr).

## [3.8.0] - 2020-09-03

### Added
- Laravel 8 support & updated versions of all dependencies [#2108](https://github.com/jenssegers/laravel-mongodb/pull/2108) by [@divine](https://github.com/divine).
