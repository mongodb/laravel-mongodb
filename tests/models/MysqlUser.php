<?php

use \Illuminate\Support\Facades\Schema;
use Jenssegers\Eloquent\Model as Eloquent;

class MysqlUser extends Eloquent {

	protected $connection = 'mysql';
    protected $table = 'users';
    protected static $unguarded = true;

    public function books()
    {
        return $this->hasMany('Book', 'author_id');
    }

    public function items()
    {
        return $this->hasMany('Item');
    }

    public function role()
    {
        return $this->hasOne('Role', 'role_id');
    }

    public function clients()
    {
        return $this->belongsToMany('Client');
    }

    public function groups()
    {
        return $this->belongsToMany('Group', null, 'users', 'groups');
    }

    /**
     * Check if we need to run the schema
     * @return [type] [description]
     */
    public static function executeSchema()
    {
        $schema = Schema::connection('mysql');

        if (!$schema->hasColumn('users', 'id'))
        {
            Schema::connection('mysql')->table('users', function($table)
            {
                $table->increments('id');
            });
        }

        if (!$schema->hasColumn('users', 'name'))
        {
            Schema::connection('mysql')->table('users', function($table)
            {
                $table->string('name');
            });
        }
    }

}
