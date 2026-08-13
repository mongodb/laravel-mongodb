<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use MongoDB\Laravel\Eloquent\Model;

#[Table('declared_key_types', keyType: 'int')]
class AttributedDeclaredKeyTypeDocument extends Model
{
    protected $keyType = 'string';
}
