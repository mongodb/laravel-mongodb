User authentication
==================

If you want to use Laravel's native Auth functionality, register this included service provider:

```php
MongoDB\Laravel\Auth\PasswordResetServiceProvider::class,
```

This service provider will slightly modify the internal `DatabaseReminderRepository` to add support for MongoDB based password reminders.

If you don't use password reminders, you don't have to register this service provider and everything else should work just fine.



