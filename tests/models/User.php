<?php

use Illuminate\Notifications\Notifiable;
use Moloquent\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    protected $dates = ['birthday', 'entry.date'];
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
