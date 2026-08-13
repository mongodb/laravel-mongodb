<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\DocumentModel;

#[Table('custom_collection')]
class AttributedTraitDocument extends Model
{
    use DocumentModel;

    protected $connection = 'mongodb';
}
