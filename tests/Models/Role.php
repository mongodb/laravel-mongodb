<?php

declare(strict_types=1);

namespace Jenssegers\Mongodb\Tests\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Role extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'roles';
    protected static $unguarded = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mysqlUser(): BelongsTo
    {
        return $this->belongsTo(MysqlUser::class);
    }
}
