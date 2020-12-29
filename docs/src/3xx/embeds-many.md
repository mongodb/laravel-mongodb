---
title: EmbedsMany
---
# EmbedsMany Relationship
If you want to embed models, rather than referencing them, you can use the `embedsMany` relation. This relation is similar to the `hasMany` relation but embeds the models inside the parent object.

**REMEMBER:** These relations return Eloquent collections, they don't return query builder objects!
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
The inverse relation is automagically available. You don't need to define this reverse relation.
```php
$book = Book::first();

$user = $book->user;
```
Inserting and updating embedded models works similar to the hasMany relation:
```php
$book = $user->books()->save(
    new Book(['title' => 'A Game of Thrones'])
);

// or
$book =
    $user->books()
         ->create(['title' => 'A Game of Thrones']);
```
You can update embedded models using their save method (available since release 2.0.0):
```php
$book = $user->books()->first();

$book->title = 'A Game of Thrones';
$book->save();
```
You can remove an embedded model by using the destroy method on the relation, or the delete method on the model (available since release 2.0.0):
```php
$book->delete();

// Similar operation
$user->books()->destroy($book);
```
If you want to add or remove an embedded model, without touching the database, you can use the associate and dissociate methods.

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
Embedded relations will return a Collection of embedded items instead of a query builder. Check out the available operations here: https://laravel.com/docs/master/collections
```