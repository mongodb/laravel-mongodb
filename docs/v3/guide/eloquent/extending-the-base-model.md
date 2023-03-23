# Extending The Base Model

This package includes a MongoDB enabled Eloquent class that you can use to define models for corresponding collections.

```php
use Jenssegers\Mongodb\Eloquent\Model;

class Book extends Model
{
    //
}
```

Just like a normal model, the MongoDB model class will know which collection to use based on the model name. For `Book`, the collection `books` will be used.

To change the collection, pass the `$collection` property:

```php
use Jenssegers\Mongodb\Eloquent\Model;

class Book extends Model
{
    protected $collection = 'my_books_collection';
}
```

**NOTE:** MongoDB documents are automatically stored with a unique ID that is stored in the `_id` property. If you wish to use your own ID, substitute the `$primaryKey` property and set it to your own primary key attribute name.

```php
use Jenssegers\Mongodb\Eloquent\Model;

class Book extends Model
{
    protected $primaryKey = 'id';
}

// MongoDB will also create _id, but the 'id' property will be used for primary key actions like find().
Book::create(['id' => 1, 'title' => 'The Fault in Our Stars']);
```

Likewise, you may define a `connection` property to override the name of the database connection that should be used when utilizing the model.

```php
use Jenssegers\Mongodb\Eloquent\Model;

class Book extends Model
{
    protected $connection = 'mongodb';
}
```