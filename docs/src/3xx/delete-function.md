---
title: DELETE
sidebarDepth: 0
---
# Deleting Data
## Destroy / Delete
Remove specific data.
```php
$user = User::find('asd834720394djfdf')->delete();

// Similar operation for embeds
$user->books()->destroy($book);
```

## Pull

Remove an item from an array.
```php
DB::collection('users')
    ->where('name', 'John')
    ->pull('items', 'boots');

$user->pull('items', 'boots');
DB::collection('users')
    ->where('name', 'John')
    ->pull('messages', [
        'from' => 'Jane Doe',
        'message' => 'Hi John',
    ]);

$user->pull('messages', [
    'from' => 'Jane Doe',
    'message' => 'Hi John',
]);
```


## Unset

Remove one or more fields from a document.
```php
DB::collection('users')
    ->where('name', 'John')
    ->unset('note');

$user->unset('note');
```