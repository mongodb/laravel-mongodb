<?php

use Jenssegers\Mongodb\Model as Eloquent;

use Illuminate\Auth\UserTrait;
use Illuminate\Auth\Reminders\RemindableTrait;
use Illuminate\Contracts\Auth\User as UserContract;
use Illuminate\Contracts\Auth\Remindable as RemindableContract;

class User extends Eloquent implements UserContract, RemindableContract {

    use UserTrait, RemindableTrait;

	protected $dates = array('birthday', 'entry.date');
	protected static $unguarded = true;

	public function books()
    {
        return $this->hasMany('Book', 'author_id');
    }

    public function mysqlBooks()
    {
        return $this->hasMany('MysqlBook', 'author_id');
    }

    public function items()
    {
        return $this->hasMany('Item');
    }

    public function role()
    {
        return $this->hasOne('Role');
    }

    public function mysqlRole()
    {
        return $this->hasOne('MysqlRole');
    }

	public function clients()
	{
		return $this->belongsToMany('Client');
	}

    public function groups()
    {
        return $this->belongsToMany('Group', null, 'users', 'groups');
    }

    public function photos()
    {
        return $this->morphMany('Photo', 'imageable');
    }

    public function addresses()
    {
        return $this->embedsMany('Address');
    }

    public function father()
    {
        return $this->embedsOne('User');
    }

    protected function getDateFormat()
    {
        return 'l jS \of F Y h:i:s A';
    }
}
