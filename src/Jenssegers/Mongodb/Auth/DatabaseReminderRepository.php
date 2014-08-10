<?php namespace Jenssegers\Mongodb\Auth;

use DateTime;
use MongoDate;

class DatabaseReminderRepository extends \Illuminate\Auth\Reminders\DatabaseReminderRepository {

	/**
	 * Build the record payload for the table.
	 *
	 * @param  string  $email
	 * @param  string  $token
	 * @return array
	 */
	protected function getPayload($email, $token)
	{
		return array('email' => $email, 'token' => $token, 'created_at' => new MongoDate);
	}

	/**
	 * Determine if the reminder has expired.
	 *
	 * @param  object  $reminder
	 * @return bool
	 */
	protected function reminderExpired($reminder)
	{
		// Convert MongoDate to a date string.
		if ($reminder['created_at'] instanceof MongoDate)
		{
			$date = new DateTime;

			$date->setTimestamp($reminder['created_at']->sec);

			$reminder['created_at'] = $date->format('Y-m-d H:i:s');
		}

		// Convert DateTime to a date string (backwards compatibility).
		elseif (is_array($reminder['created_at']))
		{
			$date = DateTime::__set_state($reminder['created_at']);

			$reminder['created_at'] = $date->format('Y-m-d H:i:s');
		}

		return parent::reminderExpired($reminder);
	}

}
