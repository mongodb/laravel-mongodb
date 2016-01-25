<?php namespace Jenssegers\Mongodb\Auth;

use MongoDB\BSON\UTCDateTime;

class DatabaseTokenRepository extends \Illuminate\Auth\Passwords\DatabaseTokenRepository
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
            $token['created_at'] = $date->format('Y-m-d H:i:s');
        } elseif (is_array($token['created_at']) and isset($token['created_at']['date'])) {
            $token['created_at'] = $token['created_at']['date'];
        }

        return parent::tokenExpired($token);
    }
}
