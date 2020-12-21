## Testing

To run the test for this package, run:

```
docker-compose up
```

## Database Testing

To reset the database after each test, add:

```php
use Illuminate\Foundation\Testing\DatabaseMigrations;
```

Also inside each test classes, add:

```php
use DatabaseMigrations;
```

Keep in mind that these traits are not yet supported:
- `use Database Transactions;`
- `use RefreshDatabase;`