<?php

class AuthTest extends TestCase {

    public function tearDown()
    {
        User::truncate();
        DB::collection('password_reminders')->truncate();
    }

    public function testAuthAttempt()
    {
        $user = User::create(array(
            'name' => 'John Doe',
            'email' => 'john@doe.com',
            'password' => Hash::make('foobar')
        ));

        $this->assertTrue(Auth::attempt(array('email' => 'john@doe.com', 'password' => 'foobar'), true));
        $this->assertTrue(Auth::check());
    }

    public function testRemind()
    {
        $mailer = Mockery::mock('Illuminate\Mail\Mailer');
        $this->app->instance('mailer', $mailer);

        $user = User::create(array(
            'name' => 'John Doe',
            'email' => 'john@doe.com',
            'password' => Hash::make('foobar')
        ));

        $mailer->shouldReceive('send')->once();
        Password::remind(array('email' => 'john@doe.com'));

        $this->assertEquals(1, DB::collection('password_reminders')->count());
        $reminder = DB::collection('password_reminders')->first();
        $this->assertEquals('john@doe.com', $reminder['email']);
        $this->assertNotNull($reminder['token']);
        $this->assertInstanceOf('MongoDate', $reminder['created_at']);

        $credentials = array(
            'email' => 'john@doe.com',
            'password' => 'foobar',
            'password_confirmation' => 'foobar',
            'token' => $reminder['token']
        );

        $response = Password::reset($credentials, function($user, $password)
        {
            $user->password = Hash::make($password);
            $user->save();
        });

        $this->assertEquals('reminders.reset', $response);
        $this->assertEquals(0, DB::collection('password_reminders')->count());
    }

}
