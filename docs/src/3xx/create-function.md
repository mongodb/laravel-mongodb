---
title: CREATE / INSERT
sidebarDepth: 0
---

## Create
To create/Insert a new data is by using the create function.
```php
User::create(['username' => 'john123', 'email' => 'john123@gmail.com']);
```

## Upsert
Using upsert will update data that exist and if doesnt exist it will create it.
```php
// Query Builder
DB::collection('users')
    ->where('name', 'John')
    ->update($data, ['upsert' => true]);

// Eloquent
$user->update($data, ['upsert' => true]);
```