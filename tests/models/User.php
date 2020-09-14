<?php
declare(strict_types=1);

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Notifications\Notifiable;
use Jenssegers\Mongodb\Eloquent\HybridRelations;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

/**
 * Class User
 * @property string $_id
 * @property string $name
 * @property string $title
 * @property int $age
 * @property \Carbon\Carbon $birthday
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class User extends Eloquent implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword, HybridRelations, Notifiable;

    protected $connection = 'mongodb';
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
        return $this->belongsToMany('Group', 'groups', 'users', 'groups', '_id', '_id', 'groups');
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

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('l jS \of F Y h:i:s A');
    }
}
