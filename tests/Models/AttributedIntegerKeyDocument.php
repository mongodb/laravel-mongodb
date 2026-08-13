<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use MongoDB\Laravel\Eloquent\Model;

#[Table('int_keys', keyType: 'int')]
class AttributedIntegerKeyDocument extends Model
{
}
