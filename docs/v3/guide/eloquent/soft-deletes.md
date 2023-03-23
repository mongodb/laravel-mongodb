# Soft Deletes

When soft deleting a model, it is not actually removed from your database. Instead, a deleted_at timestamp is set on the record.

To enable soft deletes for a model, apply the `Jenssegers\Mongodb\Eloquent\SoftDeletes` Trait to the model:

```php
use Jenssegers\Mongodb\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;
}
```

For more information check [Laravel Docs about Soft Deleting](http://laravel.com/docs/eloquent#soft-deleting).