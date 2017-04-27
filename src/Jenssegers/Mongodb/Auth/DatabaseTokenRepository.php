<?php namespace Jenssegers\Mongodb\Auth;

use DateTimeZone;
use Illuminate\Auth\Passwords\DatabaseTokenRepository as BaseDatabaseTokenRepository;
use MongoDB\BSON\UTCDateTime;

class DatabaseTokenRepository extends BaseDatabaseTokenRepository
{
    /**
     * @inheritdoc
     */
    protected function getPayload($email, $token)
    {
        return ['email' => $email, 'token' => $this->hasher->make($token), 'created_at' => new UTCDateTime(time() * 1000)];
    }

    /**
     * @inheritdoc
     */
    protected function tokenExpired($token)
    {
        // Convert UTCDateTime to a date string.
        if ($token instanceof UTCDateTime) {
            $date = $token->toDateTime();
            $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
            $token = $date->format('Y-m-d H:i:s');
        }

        return parent::tokenExpired($token);
    }
}
