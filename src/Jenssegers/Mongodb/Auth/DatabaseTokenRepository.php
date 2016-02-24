<?php namespace Jenssegers\Mongodb\Auth;

use Illuminate\Auth\Passwords\DatabaseTokenRepository as BaseDatabaseTokenRepository;
use MongoDB\BSON\UTCDateTime;

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
        return ['email' => $email, 'token' => $token, 'created_at' => new UTCDateTime(round(microtime(true) * 1000))];
    }

    /**
     * Determine if the token has expired.
     *
     * @param  array  $token
     * @return bool
     */
    protected function tokenExpired($token)
    {
        // Convert UTCDateTime to a date string.
        if ($token['created_at'] instanceof UTCDateTime) {
            $date = $token['created_at']->toDateTime();
            $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            $token['created_at'] = $date->format('Y-m-d H:i:s');
        } elseif (is_array($token['created_at']) and isset($token['created_at']['date'])) {
            $date = new DateTime($token['created_at']['date'], new DateTimeZone(isset($token['created_at']['timezone']) ? $token['created_at']['timezone'] : 'UTC'));
            $date->setTimezone(date_default_timezone_get());
            $token['created_at'] = $date->format('Y-m-d H:i:s');
        }

        return parent::tokenExpired($token);
    }
}
