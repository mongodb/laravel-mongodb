<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\MySqlBuilder;
use Illuminate\Support\Facades\Schema;
use Jenssegers\Mongodb\Eloquent\HybridRelations;

class MysqlGroup extends Eloquent
{
    use HybridRelations;

    protected $connection = 'mysql';
    protected $table = 'groups';
    protected static $unguarded = true;
    protected $primaryKey = 'name';

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(MysqlUser::class);
    }

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema(): void
    {
        /** @var MySqlBuilder $schema */
        $schema = Schema::connection('mysql');

        if (!$schema->hasTable('groups')) {
            Schema::connection('mysql')->create('groups', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('group_user')) {
            Schema::connection('mysql')->create('group_user', function (Blueprint $table) {
                $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
                $table->foreignId('group_id')->constrained('groups')->cascadeOnUpdate()->cascadeOnDelete();
            });
        }
    }
}
