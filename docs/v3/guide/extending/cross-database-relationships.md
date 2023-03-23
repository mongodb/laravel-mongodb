# Cross-Database Relationships

If you're using a hybrid MongoDB and SQL setup, you can define relationships across them.

The model will automatically return a MongoDB-related or SQL-related relation based on the type of the related model.

If you want this functionality to work both ways, your SQL-models will need to use the `Jenssegers\Mongodb\Eloquent\HybridRelations` trait.

**This functionality only works for `hasOne`, `hasMany` and `belongsTo`.**

The MySQL model should use the `HybridRelations` trait:

```php
use Jenssegers\Mongodb\Eloquent\HybridRelations;

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
use Jenssegers\Mongodb\Eloquent\Model;

class Message extends Model
{
    protected $connection = 'mongodb';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```