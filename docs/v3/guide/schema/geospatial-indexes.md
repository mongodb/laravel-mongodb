# Geospatial indexes

Geospatial indexes are handy for querying location-based documents.

They come in two forms: `2d` and `2dsphere`. Use the schema builder to add these to a collection.

```php
Schema::create('bars', function ($collection) {
    $collection->geospatial('location', '2d');
});
```

To add a `2dsphere` index:

```php
Schema::create('bars', function ($collection) {
    $collection->geospatial('location', '2dsphere');
});
```