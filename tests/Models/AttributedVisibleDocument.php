<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Attributes\Visible;
use MongoDB\Laravel\Eloquent\Model;

#[Visible(['name'])]
class AttributedVisibleDocument extends Model
{
}
