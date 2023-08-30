<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\MySqlBuilder;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Eloquent\HybridRelations;

class MysqlUser extends EloquentModel
{
    use HybridRelations;

    protected $connection = 'mysql';
    protected $table = 'users';
    protected static $unguarded = true;

    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'author_id');
    }

    public function role(): HasOne
    {
        return $this->hasOne(Role::class);
    }

    public function mysqlBooks(): HasMany
    {
        return $this->hasMany(MysqlBook::class);
    }

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema(): void
    {
        /** @var MySqlBuilder $schema */
        $schema = Schema::connection('mysql');

        if (! $schema->hasTable('users')) {
            Schema::connection('mysql')->create('users', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->timestamps();
            });
        }
    }
}
