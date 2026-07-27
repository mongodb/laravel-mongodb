# Transactions

Single-document operations are already atomic. Use transactions only when multiple documents (often across collections) must change together.

## Requirements

- A MongoDB **replica set** or **sharded cluster** — standalone `mongod` throws at runtime.
- All collections must exist **before** the transaction starts — create them in migrations.

## Usage

```php
<?php

use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\Payment;

DB::connection('mongodb')->transaction(function () use ($customerId): void {
    $order = Order::create(['customer_id' => $customerId, 'total' => 9900]);

    Payment::create([
        'order_id' => (string) $order->_id,
        'amount'   => 9900,
        'status'   => 'captured',
    ]);
});
```

## Manual control

```php
$connection = DB::connection('mongodb');
$connection->beginTransaction();

try {
    // writes...
    $connection->commit();
} catch (\Throwable $e) {
    $connection->rollBack();
    throw $e;
}
```

## When NOT to use transactions

```php
// Single-document update is atomic — no transaction needed
Post::whereKey($id)->increment('views');
Post::where('_id', $id)->update(['$inc' => ['views' => 1]]);
```

Prefer embedding related data in one document to avoid transactions — they increase latency and lock contention.

## Known limitations

- Nested transactions are not supported.
- `DatabaseTransactions` and `RefreshDatabase` testing traits are **not supported** (rely on SQL behaviour). Seed/truncate collections manually in tests instead.
- Parallel operations within a single session/transaction are not supported.
