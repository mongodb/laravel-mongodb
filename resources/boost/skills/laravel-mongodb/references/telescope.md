# Telescope

## Install

```sh
composer require laravel/telescope --dev
php artisan telescope:install
```

## Store Telescope entries on a SQL connection

Telescope stores its entries through a SQL connection. Its default is `env('DB_CONNECTION', 'mysql')`, so an application whose default connection is MongoDB must point to a SQL connection.

```
# .env
TELESCOPE_DB_CONNECTION=sqlite
```

```php
// config/telescope.php
'storage' => [
    'database' => [
        'connection' => env('TELESCOPE_DB_CONNECTION', 'sqlite'),
        'chunk' => 1000,
    ],
],
```

The Telescope migration reads that setting. Run it after pointing to the SQL connection, otherwise it targets the MongoDB connection and creates no table:

```sh
php artisan migrate
```

## Query recording

Nothing to enable. The MongoDB connection attaches its command subscriber as soon as something consumes the `QueryExecuted` event, and Telescope registers such a listener at boot. A MongoDB connection behaves like a SQL one: every command is reported.

`DB::connection('mongodb')->enableQueryLog()` is not needed for Telescope. It only controls `DB::getQueryLog()`, the in-memory query log, and it is disabled by default.

```php
// Only for DB::getQueryLog(), not for Telescope.
DB::connection('mongodb')->enableQueryLog();
```

## Run a recorded query in mongosh

Telescope shows the command as JSON, with each BSON value annotated by type: `$numberInt`, `$numberLong`, `$numberDecimal`, `$oid`, `$date`. mongosh understands that format, but the text has to be parsed before the command runs.

```js
db.runCommand(EJSON.parse(`
{
  "aggregate": "products",
  "pipeline": [
    {
      "$group": {
        "_id": "$details.color",
        "count": { "$sum": { "$numberInt": "1" } },
        "maxPrice": { "$max": "$price" }
      }
    },
    {
      "$sort": { "_id": { "$numberInt": "1" } }
    }
  ],
  "cursor": {}
}
`))
```

`EJSON.parse()` turns the annotations into real BSON types, so the server receives the same command the driver sent. Use a template literal so the double quotes need no escaping.

`runCommand()` returns the cursor document, and mongosh only displays the first batch. To browse the results, call the collection method instead:

```js
db.products.aggregate(EJSON.parse(`
[
  { "$group": { "_id": "$details.color", "count": { "$sum": { "$numberInt": "1" } } } }
]
`))
```

To write plain JavaScript instead, drop the annotations:

| Telescope JSON | mongosh |
|---|---|
| `{"$numberInt": "1"}` | `1` |
| `{"$numberLong": "42"}` | `42` |
| `{"$numberDouble": "1.5"}` | `1.5` |
| `{"$numberDecimal": "1.5"}` | `NumberDecimal("1.5")` |
| `{"$oid": "..."}` | `ObjectId("...")` |
| `{"$date": {"$numberLong": "1750000000000"}}` | `ISODate("2025-06-15T15:06:40Z")` |

A command pasted without parsing fails with `MongoServerError[InvalidPipelineOperator]: Unrecognized expression '$numberInt'`, because the annotation is read as an aggregation operator.
