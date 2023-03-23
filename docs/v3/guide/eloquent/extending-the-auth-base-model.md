# Extending the Authenticatable base model

This package includes a MongoDB Authenticatable Eloquent class `Jenssegers\Mongodb\Auth\User` that you can use to replace the default Authenticatable class `Illuminate\Foundation\Auth\User` for your `User` model.

```php
use Jenssegers\Mongodb\Auth\User as Authenticatable;

class User extends Authenticatable
{

}
```
