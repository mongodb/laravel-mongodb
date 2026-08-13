<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Touches;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

use function strtoupper;

#[Table(
    'custom_collection',
    key: 'custom_id',
    keyType: 'string',
    incrementing: false,
    dateFormat: 'U',
)]
#[WithoutTimestamps]
#[Connection('mongodb')]
#[Fillable(['name', 'title'])]
#[Hidden(['password'])]
#[Appends(['upper_name'])]
#[Touches(['author'])]
#[UseFactory(AttributedDocumentFactory::class)]
class AttributedDocument extends Model
{
    use HasFactory;

    public function getUpperNameAttribute(): string
    {
        return strtoupper((string) $this->name);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(AttributedAuthor::class);
    }
}
