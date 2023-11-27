<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\SQLiteBuilder;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Eloquent\HybridRelations;

use function assert;

class SqlUser extends EloquentModel
{
    use HybridRelations;

    protected $connection       = 'sqlite';
    protected $table            = 'users';
    protected static $unguarded = true;

    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'author_id');
    }

    public function role(): HasOne
    {
        return $this->hasOne(Role::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, relatedPivotKey: 'skills');
    }

    public function sqlBooks(): HasMany
    {
        return $this->hasMany(SqlBook::class);
    }

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema(): void
    {
        $schema = Schema::connection('sqlite');
        assert($schema instanceof SQLiteBuilder);

        $schema->dropIfExists('users');
        $schema->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });
        if (! $schema->hasTable('skill_sql_user')) {
            $schema->create('skill_sql_user', function (Blueprint $table) {
                $table->foreignIdFor(self::class)->constrained()->cascadeOnDelete();
                $table->string((new Skill())->getForeignKey());
                $table->primary([(new self())->getForeignKey(), (new Skill())->getForeignKey()]);
            });
        }
    }
}
