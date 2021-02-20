# Changelog
All notable changes to this project will be documented in this file.

## [Unreleased]

## [3.6.8] - 2021-02-21

### Changed
- (Backport) Fix query builder regression [#2204](https://github.com/jenssegers/laravel-mongodb/pull/2204) by [@divine](https://github.com/divine)

## [3.6.7] - 2020-12-18

### Changed
- (Backport) MongodbQueueServiceProvider does not use the DB Facade anymore [#2149](https://github.com/jenssegers/laravel-mongodb/pull/2149) by [@curosmj](https://github.com/curosmj)
- (Backport) Add escape regex chars to DB Presence Verifier [#1992](https://github.com/jenssegers/laravel-mongodb/pull/1992) by [@andrei-gafton-rtgt](https://github.com/andrei-gafton-rtgt).


## [3.6.6] - 2020-10-29

### Changed
- (Backport) Fix like with numeric values [#2130](https://github.com/jenssegers/laravel-mongodb/pull/2130) by [@pendexgabo](https://github.com/pendexgabo).

## [3.6.5] - 2020-08-26

### Changed
- Fix truncate to delete items in collection [#1993](https://github.com/jenssegers/laravel-mongodb/pull/1993) by [@Yahatix](https://github.com/Yahatix).
- Fix always call connection to broadcast QueryExecuted event [#2024](https://github.com/jenssegers/laravel-mongodb/pull/2024) by [@fsotomsk](https://github.com/fsotomsk).
- Fix laravel guarded error [#2082](https://github.com/jenssegers/laravel-mongodb/pull/2082) by [@fish3046](https://github.com/fish3046).

## [3.6.4] - 2020-04-23

### Added
- Add cursor [#2024](https://github.com/jenssegers/laravel-mongodb/pull/) by [@fsotomsk](https://github.com/fsotomsk).

### Changed
- Fix issue parsing millisecond-precision dates before 1970 [#2028](https://github.com/jenssegers/laravel-mongodb/pull/2028) by [@DFurnes](https://github.com/DFurnes).
- Query like on integer fields [#2020](https://github.com/jenssegers/laravel-mongodb/pull/2020) by [@ilyasokay](https://github.com/ilyasokay).
- Fix refresh() on EmbedsOne [#1996](https://github.com/jenssegers/laravel-mongodb/pull/1996) by [@stephandesouza](https://github.com/stephandesouza).

## [3.6.3] - 2020-03-04

### Changed
- Fix laravel 6 compatibility [#1979](https://github.com/jenssegers/laravel-mongodb/pull/1979) by [@divine](https://github.com/divine).
- Fix getDefaultDatabaseName to handle +srv URLs [#1976](https://github.com/jenssegers/laravel-mongodb/pull/1976) by [derekrprice](https://github.com/derekrprice).

## [3.6.2] - 2020-02-27

### Added
- Added new logic for HybridRelations::morphTo [#1835](https://github.com/jenssegers/laravel-mongodb/pull/1835) by [@stephandesouza](https://github.com/stephandesouza).
- Added using Carbon::now() [#1870](https://github.com/jenssegers/laravel-mongodb/pull/1870) by [@CurosMJ](https://github.com/CurosMJ).
- Added $localKey parameter for hasOne and hasMany [#1837](https://github.com/jenssegers/laravel-mongodb/pull/1837) by [@stephandesouza](https://github.com/stephandesouza).
- Add MustVerifyEmail trait [#1933](https://github.com/jenssegers/laravel-mongodb/pull/1933) by [@si2w](https://github.com/si2w) & [@divine](https://github.com/divine).

### Changed
- UTCDateTime conversion now includes milliseconds [#1966](https://github.com/jenssegers/laravel-mongodb/pull/1966) by [@Flambe](https://github.com/Flambe) & [@Giacomo92](https://github.com/Giacomo92).
- Allow setting hint option on QueryBuilder [#1939](https://github.com/jenssegers/laravel-mongodb/pull/1939) by [@CurosMJ](https://github.com/CurosMJ) & [@divine](https://github.com/divine).
- Fix Convert UTCDateTime to a date string when reset password [#1903](https://github.com/jenssegers/laravel-mongodb/pull/1903) by [@azizramdan](https://github.com/azizramdan).
- Fix truncate on models [#1949](https://github.com/jenssegers/laravel-mongodb/pull/1949) by [@divine](https://github.com/divine).
- Fix Carbon import [#1964](https://github.com/jenssegers/laravel-mongodb/pull/1964) by [@Christophvh](https://github.com/Christophvh) & [@Giacomo92](https://github.com/Giacomo92).
- Fix get database name from dsn [#1954](https://github.com/jenssegers/laravel-mongodb/pull/1954) by [@hlorofos](https://github.com/hlorofos) & [@divine](https://github.com/divine).
- Fix paginate in EmbedsMany [#1959](https://github.com/jenssegers/laravel-mongodb/pull/1959) by [@SamsamBabadi](https://github.com/SamsamBabadi) & [@Giacomo92](https://github.com/Giacomo92).
- Fix format exception to string in failed jobs [#1961](https://github.com/jenssegers/laravel-mongodb/pull/1961) by [@divine](https://github.com/divine).
- Fix correct import class for db in queue [#1968](https://github.com/jenssegers/laravel-mongodb/pull/1968) by [@divine](https://github.com/divine).
- Fix default database detection from dsn [#1971](https://github.com/jenssegers/laravel-mongodb/pull/1971) by [@divine](https://github.com/divine).
- Fix create collection with options [#1953](https://github.com/jenssegers/laravel-mongodb/pull/1953) by [@deviouspk](https://github.com/deviouspk) & [@divine](https://github.com/divine).

## [3.6.1] - 2019-10-31

### Added
- Added hasIndex and dropIndexIfExists

### Changed
- Improved pagination performance
- Fix for not like filters
- Correctly increment job attempts

## [3.6.0] - 2019-09-08

### Added
- Laravel 6 compatibility
