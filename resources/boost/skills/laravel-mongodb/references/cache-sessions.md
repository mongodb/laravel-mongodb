# Cache and Sessions

## Cache

```php
// config/cache.php
return [
    'default' => env('CACHE_STORE', 'mongodb'),

    'stores' => [
        'mongodb' => [
            'driver'          => 'mongodb',
            'connection'      => 'mongodb',
            'collection'      => 'cache',
            'lock_connection' => 'mongodb',
            'lock_collection' => 'cache_locks',
        ],
    ],
];
```

TTL index — expiry timestamp field is `expires_at` (not `expiration`). Cache keys stored in `_id`, no extra unique index needed.

```php
Schema::connection('mongodb')->create('cache', function (Blueprint $c): void {
    $c->expire('expires_at', 0);
});

Schema::connection('mongodb')->create('cache_locks', function (Blueprint $c): void {
    $c->expire('expires_at', 0);
});
```

## Sessions

```php
// config/session.php
return [
    'driver'     => env('SESSION_DRIVER', 'mongodb'),
    'connection' => 'mongodb',
    'table'      => 'sessions',   // collection name; keep key 'table' for Laravel compatibility
    'lifetime'   => 120,
];
```

Session IDs stored in `_id` — do not add `$c->unique('id')`.

```php
Schema::connection('mongodb')->create('sessions', function (Blueprint $c): void {
    $c->index('user_id');
    $c->index('last_activity');
    $c->expire('expires_at', 0);
});
```

## Usage

```php
use Illuminate\Support\Facades\Cache;

Cache::put('user:1', $user, now()->addMinutes(10));
Cache::remember('movies:top10', 300, fn () => Movie::orderBy('rating', 'desc')->take(10)->get());
```
