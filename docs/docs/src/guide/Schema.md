# Schema
The database driver also has (limited) schema builder support. You can easily manipulate collections and set indexes.

## Basic Usage

```php
Schema::create('users', function ($collection) {
    $collection->index('name');
    $collection->unique('email');
});
```

You can also pass all the parameters specified [in the MongoDB docs](https://docs.mongodb.com/manual/reference/method/db.collection.createIndex/#options-for-all-index-types) to the `$options` parameter:

```php
Schema::create('users', function ($collection) {
    $collection->index(
        'username',
        null,
        null,
        [
            'sparse' => true,
            'unique' => true,
            'background' => true,
        ]
    );
});
```

Inherited operations:
- create and drop
- collection
- hasCollection
- index and dropIndex (compound indexes supported as well)
- unique

MongoDB specific operations:
- background
- sparse
- expire
- geospatial

All other (unsupported) operations are implemented as dummy pass-through methods because MongoDB does not use a predefined schema.

Read more about the schema builder on [Laravel Docs](https://laravel.com/docs/6.0/migrations#tables)

## Geospatial indexes

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