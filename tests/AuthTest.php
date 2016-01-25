<?php

use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Foundation\Application;

class AuthTest extends TestCase
{

    public function tearDown()
    {
        User::truncate();
        DB::collection('password_reminders')->truncate();
    }

    public function testAuthAttempt()
    {
        $user = User::create([
            'name'     => 'John Doe',
            'email'    => 'john@doe.com',
            'password' => Hash::make('foobar'),
        ]);

        $this->assertTrue(Auth::attempt(['email' => 'john@doe.com', 'password' => 'foobar'], true));
        $this->assertTrue(Auth::check());
    }

    public function testRemind()
    {
        if (Application::VERSION >= '5.2') {
            return;
        }

        $mailer = Mockery::mock('Illuminate\Mail\Mailer');
        $tokens = $this->app->make('auth.password.tokens');
        $users = $this->app['auth']->driver()->getProvider();

        $broker = new PasswordBroker($tokens, $users, $mailer, '');

        $user = User::create([
            'name'     => 'John Doe',
            'email'    => 'john@doe.com',
            'password' => Hash::make('foobar'),
        ]);

        $mailer->shouldReceive('send')->once();
        $broker->sendResetLink(['email' => 'john@doe.com']);

        $this->assertEquals(1, DB::collection('password_resets')->count());
        $reminder = DB::collection('password_resets')->first();
        $this->assertEquals('john@doe.com', $reminder['email']);
        $this->assertNotNull($reminder['token']);
        $this->assertInstanceOf('MongoDB\BSON\UTCDateTime', $reminder['created_at']);

        $credentials = [
            'email'                 => 'john@doe.com',
            'password'              => 'foobar',
            'password_confirmation' => 'foobar',
            'token'                 => $reminder['token'],
        ];

        $response = $broker->reset($credentials, function ($user, $password) {
            $user->password = bcrypt($password);
            $user->save();
        });

        $this->assertEquals('passwords.reset', $response);
        $this->assertEquals(0, DB::collection('password_resets')->count());
    }
}
