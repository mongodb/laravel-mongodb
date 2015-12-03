<?php namespace Jenssegers\Mongodb\Auth;

use DateTime;
use MongoDate;

class DatabaseTokenRepository extends \Illuminate\Auth\Passwords\DatabaseTokenRepository {

    /**
     * Build the record payload for the table.
     *
     * @param  string  $email
     * @param  string  $token
     * @return array
     */
    protected function getPayload($email, $token)
    {
        return ['email' => $email, 'token' => $token, 'created_at' => new MongoDate];
    }

    /**
     * Determine if the token has expired.
     *
     * @param  array  $token
     * @return bool
     */
    protected function tokenExpired($token)
    {
        // Convert MongoDate to a date string.
        if ($token['created_at'] instanceof MongoDate)
        {
            $date = new DateTime;

            $date->setTimestamp($token['created_at']->sec);

            $token['created_at'] = $date->format('Y-m-d H:i:s');
        }
        elseif (is_array($token['created_at']) and isset($token['created_at']['date']))
        {
           $token['created_at'] = $token['created_at']['date'];
        }

        return parent::tokenExpired($token);
    }

}
