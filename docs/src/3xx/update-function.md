---
title: UPDATING
sidebarDepth: 0
---

## Using Upsert
Upsert might be the best uption when updating data because it updates or inserts data if data doesnt exist.
```php
// Query Builder
DB::collection('users')
    ->where('name', 'John')
    ->update(['age' => 20, 'address' => 'philippines'], ['upsert' => true]);

// Eloquent
$user->update(['age' => 20, 'address' => 'philippines'], ['upsert' => true]);
```

## Increament and Decrement
You can also update numbers using specific collumns.
```php
Cat::where('age', 3)
    ->increment('age', 1, ['group' => 'Kitty Club']);

Car::where('weight', 300)
    ->decrement('weight', 100, ['latest_change' => 'carbon fiber']);
```