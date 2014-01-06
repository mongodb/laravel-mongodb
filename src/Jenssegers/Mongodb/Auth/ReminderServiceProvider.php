<?php namespace Jenssegers\Mongodb\Auth;

use Jenssegers\Mongodb\Auth\DatabaseReminderRepository as DbRepository;

class ReminderServiceProvider extends \Illuminate\Auth\Reminders\ReminderServiceProvider {

	/**
	 * Register the reminder repository implementation.
	 *
	 * @return void
	 */
	protected function registerReminderRepository()
	{
		$this->app->bindShared('auth.reminder.repository', function($app)
		{
			$connection = $app['db']->connection();

			// The database reminder repository is an implementation of the reminder repo
			// interface, and is responsible for the actual storing of auth tokens and
			// their e-mail addresses. We will inject this table and hash key to it.
			$table = $app['config']['auth.reminder.table'];

			$key = $app['config']['app.key'];

			$expire = $app['config']->get('auth.reminder.expire', 60);

			return new DbRepository($connection, $table, $key, $expire);
		});
	}

}
