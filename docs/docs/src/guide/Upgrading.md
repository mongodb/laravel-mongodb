# Upgrading

## Upgrading from version 2 to 3

In this new major release which supports the new MongoDB PHP extension, we also moved the location of the Model class and replaced the MySQL model class with a trait.

Please change all `Jenssegers\Mongodb\Model` references to `Jenssegers\Mongodb\Eloquent\Model` either at the top of your model files or your registered alias.

```php
use Jenssegers\Mongodb\Eloquent\Model;

class User extends Model
{
    //
}
```

If you are using hybrid relations, your MySQL classes should now extend the original Eloquent model class `Illuminate\Database\Eloquent\Model` instead of the removed `Jenssegers\Eloquent\Model`.

Instead use the new `Jenssegers\Mongodb\Eloquent\HybridRelations` trait. This should make things more clear as there is only one single model class in this package.

```php
use Jenssegers\Mongodb\Eloquent\HybridRelations;

class User extends Model
{

    use HybridRelations;

    protected $connection = 'mysql';
}
```

Embedded relations now return an `Illuminate\Database\Eloquent\Collection` rather than a custom Collection class. If you were using one of the special methods that were available, convert them to Collection operations.

```php
$books = $user->books()->sortBy('title')->get();
```

## Security contact information

To report a security vulnerability, follow [these steps](https://tidelift.com/security).
