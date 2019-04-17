<?php

class DsnTest extends TestCase
{
    public function test_dsn_works()
    {
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, DsnAddress::all());
    }
}

class DsnAddress extends Address
{
    protected $connection = 'dsn_mongodb';
}
