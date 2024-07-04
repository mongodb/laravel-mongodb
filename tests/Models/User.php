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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\DocumentModel;
use MongoDB\Laravel\Eloquent\MassPrunable;

/**
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
class User extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    use DocumentModel;
    use Authenticatable;
    use CanResetPassword;
    use Notifiable;
    use MassPrunable;

    protected $primaryKey = '_id';
    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected $casts      = [
        'birthday' => 'datetime',
        'entry.date' => 'datetime',
        'member_status' => MemberStatus::class,
    ];

    protected $fillable         = [
        'name',
        'email',
        'title',
        'age',
        'birthday',
        'username',
        'member_status',
    ];
    protected static $unguarded = true;

    public function books()
    {
        return $this->hasMany(Book::class, 'author_id');
    }

    public function softs()
    {
        return $this->hasMany(Soft::class);
    }

    public function softsWithTrashed()
    {
        return $this->hasMany(Soft::class)->withTrashed();
    }

    public function sqlBooks()
    {
        return $this->hasMany(SqlBook::class, 'author_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function role()
    {
        return $this->hasOne(Role::class);
    }

    public function sqlRole()
    {
        return $this->hasOne(SqlRole::class);
    }

    public function clients()
    {
        return $this->belongsToMany(Client::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'groups', 'users', 'groups', '_id', '_id', 'groups');
    }

    public function photos()
    {
        return $this->morphMany(Photo::class, 'has_image');
    }

    public function labels()
    {
        return $this->morphToMany(Label::class, 'labelled');
    }

    public function addresses()
    {
        return $this->embedsMany(Address::class);
    }

    public function father()
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
            set: fn ($value) => Str::slug($value),
        );
    }

    public function prunable(): Builder
    {
        return $this->where('age', '>', 18);
    }
}
