<?php namespace Jenssegers\Mongodb\Auth;

use Jenssegers\Mongodb\Auth\GenericUser as GenericUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Jenssegers\Mongodb\Eloquent\Model as Model;
use Illuminate\Support\Facades\Hash;

class MongoDBUserProvider implements UserProvider
{
  /**
   * @var Model
   */
  private $model;

  public function __construct()
  {
    // Set a base connection
    $this->model = DB::connection('mongodb')->collection('users');
  }

  /**
   * Retrieve a user by their unique identifier.
   *
   * @param  mixed $identifier
   * @return \Illuminate\Contracts\Auth\Authenticatable|null
   */
  public function retrieveById($identifier)
  {
    $user = $this->model->find($identifier);

    return $this->getGenericUser($user);
  }

  /**
   * Retrieve a user by their unique identifier and "remember me" token.
   *
   * @param  mixed  $identifier
   * @param  string $token
   * @return \Illuminate\Contracts\Auth\Authenticatable|null
   */
  public function retrieveByToken($identifier, $token)
  {
    $user = $this->model
      ->where('_id', (string) $identifier)
      ->where('remember_token', $token)
      ->first();

    return $this->getGenericUser($user);
  }

  /**
   * Update the "remember me" token for the given user in storage.
   *
   * @param  \Illuminate\Contracts\Auth\Authenticatable $user
   * @param  string                                     $token
   * @return void
   */
  public function updateRememberToken(UserContract $user, $token)
  {
    $this->model->where('_id', $user->getAuthIdentifier())
      ->update(['remember_token' => $token]);
  }

  /**
   * Retrieve a user by the given credentials.
   *
   * @param  array $credentials
   * @return \Illuminate\Contracts\Auth\Authenticatable|null
   */
  public function retrieveByCredentials(array $credentials)
  {

    // First we will add each credential element to the query as a where clause.
    // Then we can execute the query and, if we found a user, return it in a
    // generic "user" object that will be utilized by the Guard instances.
    $query = $this->model;

    foreach ($credentials as $key => $value) {
      if (!Str::contains($key, 'password')) {
        $query->where($key, $value);
      }
    }

    // Now we are ready to execute the query to see if we have an user matching
    // the given credentials. If not, we will just return nulls and indicate
    // that there are no matching users for these given credential arrays.
    $user = $query->first();


    return $this->getGenericUser($user);
  }

  /**
   * Get the generic user.
   *
   * @param  mixed $user
   * @return \Illuminate\Auth\GenericUser|null
   */
  protected function getGenericUser($user)
  {
    if ($user !== null) {
      return new GenericUser((array)$user);
    }
  }

  /**
   * Validate a user against the given credentials.
   *
   * @param  \Illuminate\Contracts\Auth\Authenticatable $user
   * @param  array                                      $credentials
   * @return bool
   */
  public function validateCredentials(UserContract $user, array $credentials)
  {
    $plain = $credentials['password'];

    return Hash::check($plain, $user->getAuthPassword());
  }
}
