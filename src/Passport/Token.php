<?php

namespace Moloquent\Passport;

use Moloquent\Eloquent\Model;

class Token extends Model
{
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'oauth_access_tokens';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The guarded attributes on the model.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'revoked' => 'bool',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'expires_at',
    ];

    /**
     * Overwrite scopes setter to handle default passport JSON string
     * and save native array.
     *
     * @param mixed $scopes
     */
    public function setScopesAttribute($scopes)
    {
        if (is_string($scopes)) {
            $scopes = json_decode($scopes, true);
        }

        // If successfully decoded into array, then it will be saved as array.
        // If still string, will be converted to array to preserve consistency.
        $this->attributes['scopes'] = (array) $scopes;
    }

    /**
     * Get the client that the token belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Determine if the token has a given scope.
     *
     * @param string $scope
     *
     * @return bool
     */
    public function can($scope)
    {
        return in_array('*', $this->scopes) ||
               array_key_exists($scope, array_flip($this->scopes));
    }

    /**
     * Determine if the token is missing a given scope.
     *
     * @param string $scope
     *
     * @return bool
     */
    public function cant($scope)
    {
        return !$this->can($scope);
    }

    /**
     * Revoke the token instance.
     *
     * @return void
     */
    public function revoke()
    {
        $this->forceFill(['revoked' => true])->save();
    }

    /**
     * Determine if the token is a transient JWT token.
     *
     * @return bool
     */
    public function transient()
    {
        return false;
    }
}
