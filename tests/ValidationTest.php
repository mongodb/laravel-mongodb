<?php

class ValidationTest extends TestCase {

    public function tearDown()
    {
        User::truncate();
    }

    public function testUnique()
    {
        $validator = Validator::make(
            array('name' => 'John Doe'),
            array('name' => 'required|unique:users')
        );
        $this->assertFalse($validator->fails());

        User::create(array('name' => 'John Doe'));

        $validator = Validator::make(
            array('name' => 'John Doe'),
            array('name' => 'required|unique:users')
        );
        $this->assertTrue($validator->fails());
    }

}
