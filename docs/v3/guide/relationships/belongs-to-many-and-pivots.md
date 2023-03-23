# belongsToMany and pivots

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