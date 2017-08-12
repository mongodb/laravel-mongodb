<?php

class ValidationTest extends TestCase
{
    public function tearDown()
    {
        User::truncate();
    }

    public function testUnique()
    {
        $validator = Validator::make(
            ['name' => 'John Doe'],
            ['name' => 'required|unique:users']
        );
        $this->assertFalse($validator->fails());

        User::create(['name' => 'John Doe']);

        $validator = Validator::make(
            ['name' => 'John Doe'],
            ['name' => 'required|unique:users']
        );
        $this->assertTrue($validator->fails());

        $validator = Validator::make(
            ['name' => 'John doe'],
            ['name' => 'required|unique:users']
        );
        $this->assertTrue($validator->fails());

        $validator = Validator::make(
            ['name' => 'john doe'],
            ['name' => 'required|unique:users']
        );
        $this->assertTrue($validator->fails());

        $validator = Validator::make(
            ['name' => 'test doe'],
            ['name' => 'required|unique:users']
        );
        $this->assertFalse($validator->fails());
    }

    public function testExists()
    {
        $validator = Validator::make(
            ['name' => 'John Doe'],
            ['name' => 'required|exists:users']
        );
        $this->assertTrue($validator->fails());

        User::create(['name' => 'John Doe']);
        User::create(['name' => 'Test Name']);

        $validator = Validator::make(
            ['name' => 'John Doe'],
            ['name' => 'required|exists:users']
        );
        $this->assertFalse($validator->fails());

        $validator = Validator::make(
            ['name' => 'john Doe'],
            ['name' => 'required|exists:users']
        );
        $this->assertFalse($validator->fails());

        $validator = Validator::make(
            ['name' => ['test name', 'john doe']],
            ['name' => 'required|exists:users']
        );
        $this->assertFalse($validator->fails());
    }
}
