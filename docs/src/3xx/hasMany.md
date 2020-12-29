---
title: hasMany
sidebarDepth: 0
---
# HasMany Relationship
We use hasMany when table objects are separated with each other.  
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

### belongsToMany and pivots
The belongsToMany relation will not use a pivot "table" but will push id's to a related_ids attribute instead. This makes the second parameter for the belongsToMany method useless.

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