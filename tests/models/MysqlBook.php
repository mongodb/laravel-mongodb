<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Jenssegers\Mongodb\Eloquent\HybridRelations;

class MysqlBook extends Eloquent
{
    use HybridRelations;

    protected $connection = 'mysql';
    protected $table = 'books';
    protected static $unguarded = true;
    protected $primaryKey = 'title';

    public function author(): BelongsTo
    {
        return $this->belongsTo('User', 'author_id');
    }

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema(): void
    {
        /** @var \Illuminate\Database\Schema\MySqlBuilder $schema */
        $schema = Schema::connection('mysql');

        if (! $schema->hasTable('books')) {
            Schema::connection('mysql')->create('books', function (Blueprint $table) {
                $table->string('title');
                $table->string('author_id')->nullable();
                $table->integer('mysql_user_id')->unsigned()->nullable();
                $table->timestamps();
            });
        }
    }
}
