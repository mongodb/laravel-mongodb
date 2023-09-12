Eloquent Models
===============

Previous: [Installation and configuration](install.md)

This package includes a MongoDB enabled Eloquent class that you can use to define models for corresponding collections.

### Extending the base model

To get started, create a new model class in your `app\Models\` directory.

```php
namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Book extends Model
{
    //
}
```

Just like a normal model, the MongoDB model class will know which collection to use based on the model name. For `Book`, the collection `books` will be used.

To change the collection, pass the `$collection` property:

```php
use MongoDB\Laravel\Eloquent\Model;

class Book extends Model
{
    protected $collection = 'my_books_collection';
}
```

**NOTE:** MongoDB documents are automatically stored with a unique ID that is stored in the `_id` property. If you wish to use your own ID, substitute the `$primaryKey` property and set it to your own primary key attribute name.

```php
use MongoDB\Laravel\Eloquent\Model;

class Book extends Model
{
    protected $primaryKey = 'id';
}

// MongoDB will also create _id, but the 'id' property will be used for primary key actions like find().
Book::create(['id' => 1, 'title' => 'The Fault in Our Stars']);
```

Likewise, you may define a `connection` property to override the name of the database connection that should be used when utilizing the model.

```php
use MongoDB\Laravel\Eloquent\Model;

class Book extends Model
{
    protected $connection = 'mongodb';
}
```

### Soft Deletes

When soft deleting a model, it is not actually removed from your database. Instead, a `deleted_at` timestamp is set on the record.

To enable soft delete for a model, apply the `MongoDB\Laravel\Eloquent\SoftDeletes` Trait to the model:

```php
use MongoDB\Laravel\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;
}
```

For more information check [Laravel Docs about Soft Deleting](http://laravel.com/docs/eloquent#soft-deleting).

### Prunable

`Prunable` and `MassPrunable` traits are Laravel features to automatically remove models from your database. You can use
`Illuminate\Database\Eloquent\Prunable` trait to remove models one by one. If you want to remove models in bulk, you need
to use the `MongoDB\Laravel\Eloquent\MassPrunable` trait instead: it will be more performant but can break links with
other documents as it does not load the models.

```php
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\MassPrunable;

class Book extends Model
{
    use MassPrunable;
}
```

For more information check [Laravel Docs about Pruning Models](http://laravel.com/docs/eloquent#pruning-models).

### Dates

Eloquent allows you to work with Carbon or DateTime objects instead of MongoDate objects. Internally, these dates will be converted to MongoDate objects when saved to the database.

```php
use MongoDB\Laravel\Eloquent\Model;

class User extends Model
{
    protected $casts = ['birthday' => 'datetime'];
}
```

This allows you to execute queries like this:

```php
$users = User::where(
    'birthday', '>',
    new DateTime('-18 years')
)->get();
```

### Extending the Authenticatable base model

This package includes a MongoDB Authenticatable Eloquent class `MongoDB\Laravel\Auth\User` that you can use to replace the default Authenticatable class `Illuminate\Foundation\Auth\User` for your `User` model.

```php
use MongoDB\Laravel\Auth\User as Authenticatable;

class User extends Authenticatable
{

}
```

### Guarding attributes

When choosing between guarding attributes or marking some as fillable, Taylor Otwell prefers the fillable route.
This is in light of [recent security issues described here](https://blog.laravel.com/security-release-laravel-61835-7240).

Keep in mind guarding still works, but you may experience unexpected behavior.

Schema
------

The database driver also has (limited) schema builder support. You can easily manipulate collections and set indexes.

### Basic Usage

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

-   create and drop
-   collection
-   hasCollection
-   index and dropIndex (compound indexes supported as well)
-   unique

MongoDB specific operations:

-   background
-   sparse
-   expire
-   geospatial

All other (unsupported) operations are implemented as dummy pass-through methods because MongoDB does not use a predefined schema.

Read more about the schema builder on [Laravel Docs](https://laravel.com/docs/10.x/migrations#tables)

### Geospatial indexes

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

Relationships
-------------

### Basic Usage

The only available relationships are:

-   hasOne
-   hasMany
-   belongsTo
-   belongsToMany

The MongoDB-specific relationships are:

-   embedsOne
-   embedsMany

Here is a small example:

```php
use MongoDB\Laravel\Eloquent\Model;

class User extends Model
{
    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
```

The inverse relation of `hasMany` is `belongsTo`:

```php
use MongoDB\Laravel\Eloquent\Model;

class Item extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

### belongsToMany and pivots

The belongsToMany relation will not use a pivot "table" but will push id's to a __related_ids__ attribute instead. This makes the second parameter for the belongsToMany method useless.

If you want to define custom keys for your relation, set it to `null`:

```php
use MongoDB\Laravel\Eloquent\Model;

class User extends Model
{
    public function groups()
    {
        return $this->belongsToMany(
            Group::class, null, 'user_ids', 'group_ids'
        );
    }
}
```

### EmbedsMany Relationship

If you want to embed models, rather than referencing them, you can use the `embedsMany` relation. This relation is similar to the `hasMany` relation but embeds the models inside the parent object.

**REMEMBER**: These relations return Eloquent collections, they don't return query builder objects!

```php
use MongoDB\Laravel\Eloquent\Model;

class User extends Model
{
    public function books()
    {
        return $this->embedsMany(Book::class);
    }
}
```

You can access the embedded models through the dynamic property:

```php
$user = User::first();

foreach ($user->books as $book) {
    //
}
```

The inverse relation is auto*magically* available. You don't need to define this reverse relation.

```php
$book = Book::first();

$user = $book->user;
```

Inserting and updating embedded models works similar to the `hasMany` relation:

```php
$book = $user->books()->save(
    new Book(['title' => 'A Game of Thrones'])
);

// or
$book =
    $user->books()
         ->create(['title' => 'A Game of Thrones']);
```

You can update embedded models using their `save` method (available since release 2.0.0):

```php
$book = $user->books()->first();

$book->title = 'A Game of Thrones';
$book->save();
```

You can remove an embedded model by using the `destroy` method on the relation, or the `delete` method on the model (available since release 2.0.0):

```php
$book->delete();

// Similar operation
$user->books()->destroy($book);
```

If you want to add or remove an embedded model, without touching the database, you can use the `associate` and `dissociate` methods.

To eventually write the changes to the database, save the parent object:

```php
$user->books()->associate($book);
$user->save();
```

Like other relations, embedsMany assumes the local key of the relationship based on the model name. You can override the default local key by passing a second argument to the embedsMany method:

```php
use MongoDB\Laravel\Eloquent\Model;

class User extends Model
{
    public function books()
    {
        return $this->embedsMany(Book::class, 'local_key');
    }
}
```

Embedded relations will return a Collection of embedded items instead of a query builder. Check out the available operations here: https://laravel.com/docs/master/collections

### EmbedsOne Relationship

The embedsOne relation is similar to the embedsMany relation, but only embeds a single model.

```php
use MongoDB\Laravel\Eloquent\Model;

class Book extends Model
{
    public function author()
    {
        return $this->embedsOne(Author::class);
    }
}
```

You can access the embedded models through the dynamic property:

```php
$book = Book::first();
$author = $book->author;
```

Inserting and updating embedded models works similar to the `hasOne` relation:

```php
$author = $book->author()->save(
    new Author(['name' => 'John Doe'])
);

// Similar
$author =
    $book->author()
         ->create(['name' => 'John Doe']);
```

You can update the embedded model using the `save` method (available since release 2.0.0):

```php
$author = $book->author;

$author->name = 'Jane Doe';
$author->save();
```

You can replace the embedded model with a new model like this:

```php
$newAuthor = new Author(['name' => 'Jane Doe']);

$book->author()->save($newAuthor);
```

Cross-Database Relationships
----------------------------

If you're using a hybrid MongoDB and SQL setup, you can define relationships across them.

The model will automatically return a MongoDB-related or SQL-related relation based on the type of the related model.

If you want this functionality to work both ways, your SQL-models will need to use the `MongoDB\Laravel\Eloquent\HybridRelations` trait.

**This functionality only works for `hasOne`, `hasMany` and `belongsTo`.**

The SQL model should use the `HybridRelations` trait:

```php
use MongoDB\Laravel\Eloquent\HybridRelations;

class User extends Model
{
    use HybridRelations;

    protected $connection = 'mysql';

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
```

Within your MongoDB model, you should define the relationship:

```php
use MongoDB\Laravel\Eloquent\Model;

class Message extends Model
{
    protected $connection = 'mongodb';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```


