<?php

use \Illuminate\Support\Facades\Schema;
use Jenssegers\Eloquent\Model as Eloquent;

class MysqlRole extends Eloquent {

    protected $connection = 'mysql';
	protected $table = 'roles';
	protected static $unguarded = true;

    public function user()
    {
    	return $this->belongsTo('User');
    }

    public function mysqlUser()
    {
    	return $this->belongsTo('MysqlUser');
    }

    /**
     * Check if we need to run the schema
     * @return [type] [description]
     */
    public static function executeSchema()
    {
        $schema = Schema::connection('mysql');

        if (!$schema->hasTable('roles'))
        {
            Schema::connection('mysql')->create('roles', function($table)
            {
                $table->string('type');
                $table->string('user_id');
                $table->timestamps();
            });
        }
    }

}
