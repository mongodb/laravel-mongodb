<?php

namespace Jenssegers\Mongodb\Auth;

use DateTime;
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
        return [
            'email' => $email,
            'token' => $this->hasher->make($token),
            'created_at' => new UTCDateTime(time() * 1000),
        ];
    }

    /**
     * @inheritdoc
     */
    protected function tokenExpired($createdAt)
    {
        // Convert UTCDateTime to a date string.
        if ($createdAt instanceof UTCDateTime) {
            $date = $createdAt->toDateTime();
            $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
            $createdAt = $date->format('Y-m-d H:i:s');
        } elseif (is_array($createdAt) && isset($createdAt['date'])) {
            $date = new DateTime($createdAt['date'], new DateTimeZone(isset($createdAt['timezone']) ? $createdAt['timezone'] : 'UTC'));
            $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
            $createdAt = $date->format('Y-m-d H:i:s');
        }

        return parent::tokenExpired($createdAt);
    }
}
