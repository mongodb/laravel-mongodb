Transactions
============

Transactions require MongoDB version ^4.0 as well as deployment of replica set or sharded clusters. You can find more information [in the MongoDB docs](https://docs.mongodb.com/manual/core/transactions/)

```php
DB::transaction(function () {
    User::create(['name' => 'john', 'age' => 19, 'title' => 'admin', 'email' => 'john@example.com']);
    DB::collection('users')->where('name', 'john')->update(['age' => 20]);
    DB::collection('users')->where('name', 'john')->delete();
});
```

```php
// begin a transaction
DB::beginTransaction();
User::create(['name' => 'john', 'age' => 19, 'title' => 'admin', 'email' => 'john@example.com']);
DB::collection('users')->where('name', 'john')->update(['age' => 20]);
DB::collection('users')->where('name', 'john')->delete();

// commit changes
DB::commit();
```

To abort a transaction, call the `rollBack` method at any point during the transaction:

```php
DB::beginTransaction();
User::create(['name' => 'john', 'age' => 19, 'title' => 'admin', 'email' => 'john@example.com']);

// Abort the transaction, discarding any data created as part of it
DB::rollBack();
```

**NOTE:** Transactions in MongoDB cannot be nested. DB::beginTransaction() function will start new transactions in a new created or existing session and will raise the RuntimeException when transactions already exist. See more in MongoDB official docs [Transactions and Sessions](https://www.mongodb.com/docs/manual/core/transactions/#transactions-and-sessions)

```php
DB::beginTransaction();
User::create(['name' => 'john', 'age' => 20, 'title' => 'admin']);

// This call to start a nested transaction will raise a RuntimeException
DB::beginTransaction();
DB::collection('users')->where('name', 'john')->update(['age' => 20]);
DB::commit();
DB::rollBack();
```

Database Testing
----------------

For testing, the traits `Illuminate\Foundation\Testing\DatabaseTransactions` and `Illuminate\Foundation\Testing\RefreshDatabase` are not yet supported.
Instead, create migrations and use the `DatabaseMigrations` trait to reset the database after each test:

```php
use Illuminate\Foundation\Testing\DatabaseMigrations;
```
