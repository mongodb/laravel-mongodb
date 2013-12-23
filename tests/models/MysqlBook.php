<?php

use \Illuminate\Support\Facades\Schema;
use Jenssegers\Eloquent\Model as Eloquent;

class MysqlBook extends Eloquent {

    protected $connection = 'mysql';
	protected $table = 'books';
	protected static $unguarded = true;
	protected $primaryKey = 'title';

    public function author()
    {
        return $this->belongsTo('User', 'author_id');
    }

    /**
     * Check if we need to run the schema
     * @return [type] [description]
     */
    public static function executeSchema()
    {
        $schema = Schema::connection('mysql');

        if (!$schema->hasTable('books'))
        {
            Schema::connection('mysql')->create('books', function($table)
            {
                $table->string('title');
                $table->string('author_id');
                $table->timestamps();
            });
        }
    }

}
