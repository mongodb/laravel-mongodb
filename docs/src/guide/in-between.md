---
title: IN BETWEEN
sidebarDepth: 2
---
Note: We need to use this library to work on dates.
```php
use Carbon\Carbon;
use \MongoDB\BSON\UTCDateTime as DateTime;
```
## Get specific date
Getting the specific dates.
```php
$start = new DateTime(Carbon::parse($request->input('date'))->subDays(1));
$to = new DateTime(Carbon::parse($request->input('date'))->addDays(1));

$data = Test::where('date','>=', $start)
    ->where('date','<=',$to)
    ->get();

return $data;
```

## using `whereBetween`
Use whereBetween to find data in between this dates.
```php
// we use the use \MongoDB\BSON\UTCDateTime as DateTime; to make this work here.
$start = new DateTime(Carbon::parse($request->input('date_from')));
$to = new DateTime(Carbon::parse($request->input('date_to')));

$data = Test::whereBetween('date', array($start, $to))->get();

return $data;
```
## or use `where`
This works just like whereBetween but using where
```php
// we use the use \MongoDB\BSON\UTCDateTime as DateTime; to make this work here.
$start = new DateTime(Carbon::parse($request->input('date_from')));
$to = new DateTime(Carbon::parse($request->input('date_to')));

$data = Test::where('date','>=', $start)
    ->where('date','<=',$to)
    ->get();

return $data;
```