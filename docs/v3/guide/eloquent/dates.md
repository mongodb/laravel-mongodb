# Dates

Eloquent allows you to work with Carbon or DateTime objects instead of MongoDate objects. Internally, these dates will be converted to MongoDate objects when saved to the database.

```php
use Jenssegers\Mongodb\Eloquent\Model;

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