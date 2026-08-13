<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use MongoDB\Laravel\Eloquent\Model;

#[Guarded(['secret'])]
class AttributedGuardedDocument extends Model
{
}
