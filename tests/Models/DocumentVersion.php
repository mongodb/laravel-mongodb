<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\HasDocumentVersion;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

/** @property int __v */
class DocumentVersion extends Eloquent
{
    use HasDocumentVersion;

    protected $connection       = 'mongodb';
    protected $collection       = 'documentVersion';
    protected static $unguarded = true;
}
