<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use MongoDB\Laravel\Eloquent\HybridRelations;
use MongoDB\Laravel\Eloquent\Model as Eloquent;
use MongoDB\Laravel\Relations\EmbedsMany;
use MongoDB\Laravel\Relations\EmbedsOne;

/**
 * Class User.
 *
 * @property string $_id
 * @property string $name
 * @property string $email
 * @property string $title
 * @property int $age
 * @property Carbon $birthday
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string $username
 * @property MemberStatus member_status
 */
class User extends Eloquent implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable;
    use CanResetPassword;
    use HybridRelations;
    use Notifiable;

    protected $connection = 'mongodb';
    protected $casts = [
        'birthday' => 'datetime',
        'entry.date' => 'datetime',
        'member_status' => MemberStatus::class,
    ];
    protected static $unguarded = true;

    public function books()
    {
        return $this->hasMany(Book::class, 'author_id');
    }

    public function mysqlBooks()
    {
        return $this->hasMany(MysqlBook::class, 'author_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function role()
    {
        return $this->hasOne(Role::class);
    }

    public function mysqlRole()
    {
        return $this->hasOne(MysqlRole::class);
    }

    public function clients()
    {
        return $this->belongsToMany(Client::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'groups', 'userIds', 'groupIds', '_id', '_id', 'groups');
    }

    public function photos()
    {
        return $this->morphMany(Photo::class, 'has_image');
    }

    public function addresses(): EmbedsMany
    {
        return $this->embedsMany(Address::class);
    }

    public function father(): EmbedsOne
    {
        return $this->embedsOne(self::class);
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('l jS \of F Y h:i:s A');
    }

    protected function username(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
            set: fn ($value) => Str::slug($value)
        );
    }

    public function getFooAttribute()
    {
        return 'normal attribute';
    }

    public function foo()
    {
        return 'normal function';
    }
}
