<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\SQLiteBuilder;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Eloquent\HybridRelations;
use MongoDB\Laravel\Relations\MorphTo;

use function assert;

class SqlPhoto extends EloquentModel
{
    use HybridRelations;

    protected $connection       = 'sqlite';
    protected $table            = 'photo';
    protected $fillable = ['url'];

    public function hasImage(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema()
    {
        $schema = Schema::connection('sqlite');
        assert($schema instanceof SQLiteBuilder);

        $schema->dropIfExists('photo');
        $schema->create('photo', function (Blueprint $table) {
            $table->increments('id');
            $table->string('url');
            $table->string('has_image_id')->nullable();
            $table->string('has_image_type')->nullable();
            $table->timestamps();
        });
    }
}
