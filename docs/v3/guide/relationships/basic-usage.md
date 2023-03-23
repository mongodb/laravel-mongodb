# Basic Usage

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