# Relationships

## Basic Usage

The only available relationships are:
 - hasOne
 - hasMany
 - belongsTo
 - belongsToMany

The MongoDB-specific relationships are:
 - embedsOne
 - embedsMany

Here is a small example:

```php
use Jenssegers\Mongodb\Eloquent\Model;

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
use Jenssegers\Mongodb\Eloquent\Model;

class Item extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

## belongsToMany and pivots

The belongsToMany relation will not use a pivot "table" but will push id's to a __related_ids__ attribute instead. This makes the second parameter for the belongsToMany method useless.

If you want to define custom keys for your relation, set it to `null`:

```php
use Jenssegers\Mongodb\Eloquent\Model;

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

## EmbedsMany Relationship

If you want to embed models, rather than referencing them, you can use the `embedsMany` relation. This relation is similar to the `hasMany` relation but embeds the models inside the parent object.

**REMEMBER**: These relations return Eloquent collections, they don't return query builder objects!

```php
use Jenssegers\Mongodb\Eloquent\Model;

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
use Jenssegers\Mongodb\Eloquent\Model;

class User extends Model
{
    public function books()
    {
        return $this->embedsMany(Book::class, 'local_key');
    }
}
```

Embedded relations will return a Collection of embedded items instead of a query builder. Check out the available operations here: https://laravel.com/docs/master/collections


## EmbedsOne Relationship

The embedsOne relation is similar to the embedsMany relation, but only embeds a single model.

```php
use Jenssegers\Mongodb\Eloquent\Model;

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