<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Auth;

use DateTime;
use DateTimeZone;
use Illuminate\Auth\Passwords\DatabaseTokenRepository as BaseDatabaseTokenRepository;
use Illuminate\Support\Facades\Date;
use MongoDB\BSON\UTCDateTime;

use function date_default_timezone_get;
use function is_array;

class DatabaseTokenRepository extends BaseDatabaseTokenRepository
{
    /** @inheritdoc */
    protected function getPayload($email, $token)
    {
        return [
            'email' => $email,
            'token' => $this->hasher->make($token),
            'created_at' => new UTCDateTime(Date::now()),
        ];
    }

    /** @inheritdoc */
    protected function tokenExpired($createdAt)
    {
        $createdAt = $this->convertDateTime($createdAt);

        return parent::tokenExpired($createdAt);
    }

    /** @inheritdoc */
    protected function tokenRecentlyCreated($createdAt)
    {
        $createdAt = $this->convertDateTime($createdAt);

        return parent::tokenRecentlyCreated($createdAt);
    }

    private function convertDateTime($createdAt)
    {
        // Convert UTCDateTime to a date string.
        if ($createdAt instanceof UTCDateTime) {
            $date = $createdAt->toDateTime();
            $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
            $createdAt = $date->format('Y-m-d H:i:s');
        } elseif (is_array($createdAt) && isset($createdAt['date'])) {
            $date = new DateTime($createdAt['date'], new DateTimeZone($createdAt['timezone'] ?? 'UTC'));
            $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
            $createdAt = $date->format('Y-m-d H:i:s');
        }

        return $createdAt;
    }
}
