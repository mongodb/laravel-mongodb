<?php namespace Jenssegers\Mongodb\Auth;

class DatabaseReminderRepository extends \Illuminate\Auth\Reminders\DatabaseReminderRepository {

	/**
	 * Determine if the reminder has expired.
	 *
	 * @param  object  $reminder
	 * @return bool
	 */
	protected function reminderExpired($reminder)
	{
		// Convert to array so that we can pass it to the parent method
		if (is_object($reminder))
		{
			$reminder = (array) $reminder;
		}

		// Convert the DateTime object that got saved to MongoDB
		if (is_array($reminder['created_at']))
		{
			$reminder['created_at'] = $reminder['created_at']['date'] + $reminder['created_at']['timezone'];
		}

		return parent::reminderExpired($reminder);
	}

}
