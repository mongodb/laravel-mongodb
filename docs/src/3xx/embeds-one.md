---
title: embedsOne
---
# EmbedsOne Relationship
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
Inserting and updating embedded models works similar to the hasOne relation:
```php
$author = $book->author()->save(
    new Author(['name' => 'John Doe'])
);

// Similar
$author =
    $book->author()
         ->create(['name' => 'John Doe']);
```
You can update the embedded model using the save method (available since release 2.0.0):
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