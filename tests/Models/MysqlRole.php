<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\MySqlBuilder;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Eloquent\HybridRelations;

use function assert;

class MysqlRole extends EloquentModel
{
    use HybridRelations;

    protected $connection       = 'mysql';
    protected $table            = 'roles';
    protected static $unguarded = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mysqlUser(): BelongsTo
    {
        return $this->belongsTo(MysqlUser::class);
    }

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema()
    {
        $schema = Schema::connection('mysql');
        assert($schema instanceof MySqlBuilder);

        if ($schema->hasTable('roles')) {
            return;
        }

        Schema::connection('mysql')->create('roles', function (Blueprint $table) {
            $table->string('type');
            $table->string('user_id');
            $table->timestamps();
        });
    }
}
