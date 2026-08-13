<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use MongoDB\Laravel\Eloquent\Model;

#[Unguarded]
class AttributedUnguardedDocument extends Model
{
}
