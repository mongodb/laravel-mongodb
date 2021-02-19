# Changelog
All notable changes to this project will be documented in this file.

## [Unreleased]

## [3.7.3] - 2021-02-21

### Changed
- (Backport) Fix query builder regression [#2204](https://github.com/jenssegers/laravel-mongodb/pull/2204) by [@divine](https://github.com/divine)

## [3.7.2] - 2020-12-18

### Changed
- (Backport) MongodbQueueServiceProvider does not use the DB Facade anymore [#2149](https://github.com/jenssegers/laravel-mongodb/pull/2149) by [@curosmj](https://github.com/curosmj)
- (Backport) Add escape regex chars to DB Presence Verifier [#1992](https://github.com/jenssegers/laravel-mongodb/pull/1992) by [@andrei-gafton-rtgt](https://github.com/andrei-gafton-rtgt).

## [3.7.1] - 2020-10-29

### Changed
- (Backport) Fix like with numeric values [#2131](https://github.com/jenssegers/laravel-mongodb/pull/2131) by [@pendexgabo](https://github.com/pendexgabo).

## [3.7.0] - 2020-09-18

### Added
- Laravel 7 support by [@divine](https://github.com/divine).

### Changed
- Updated versions of all dependencies by [@divine](https://github.com/divine).

### Removed
- shouldUseCollections function by [@divine](https://github.com/divine).
