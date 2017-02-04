<?php namespace Jenssegers\Mongodb\Auth;

use Illuminate\Auth\Passwords\DatabaseTokenRepository as BaseDatabaseTokenRepository;
use MongoDB\BSON\UTCDateTime;
use DateTime;
use DateTimeZone;

class DatabaseTokenRepository extends BaseDatabaseTokenRepository
{
    /**
     * Build the record payload for the table.
     *
     * @param  string  $email
     * @param  string  $token
     * @return array
     */
    protected function getPayload($email, $token)
    {
        return [
            'email' => $email,
            'token' => $this->isNeedToTriggerNewMethod() ? $this->hasher->make($token) : $token,
            'created_at' => new UTCDateTime(round(microtime(true) * 1000))
        ];
    }

    /**
     * Determine if the token has expired.
     *
     * @param  array  $token
     * @return bool
     */
    protected function tokenExpired($token)
    {
        $token = $this->isNeedToTriggerNewMethod() ? $this->newTokenExpirationCheck($token) : $this->oldTokenExpirationCheck($token);

        return parent::tokenExpired($token);
    }

    /**
     * Keep this logic as a backwards compatibility for Laravel 5.3
     *
     * Token expiration check for Laravel 5.3
     *
     * @param $token
     */
    protected function oldTokenExpirationCheck($token)
    {
        if ($token['created_at'] instanceof UTCDateTime) {
            $date = $token['created_at']->toDateTime();
            $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
            $token['created_at'] = $date->format('Y-m-d H:i:s');
        } elseif (is_array($token['created_at']) and isset($token['created_at']['date'])) {
            $date = new DateTime($token['created_at']['date'], new DateTimeZone(isset($token['created_at']['timezone']) ? $token['created_at']['timezone'] : 'UTC'));
            $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
            $token['created_at'] = $date->format('Y-m-d H:i:s');
        }

        return $token;
    }

    /**
     * Starting from Laravel 5.4, token will be passed as
     * MongoDB\BSON\UTCDateTime object
     *
     * Token expiration check for Laravel 5.4
     *
     * @param $token
     *
     * @return string
     */
    protected function newTokenExpirationCheck($token)
    {
        if ($token instanceof UTCDateTime) {
            $date = $token->toDateTime();
            $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
            $token = $date->format('Y-m-d H:i:s');
        }

        return $token;
    }

    /**
     * Retrieve temp version check until new dot released for 5.4+
     *
     * @return string
     */
    protected function isNeedToTriggerNewMethod()
    {
        $version = explode('.', \App::version());

        return ($version[0] >= 5 && $version[1] >= 4) ? true : false;
    }
}
