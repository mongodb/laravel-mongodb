<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests;

use Illuminate\Support\Facades\Validator;
use MongoDB\BSON\Regex;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Validation\DatabasePresenceVerifier;

class ValidationTest extends TestCase
{
    public function tearDown(): void
    {
        User::truncate();

        parent::tearDown();
    }

    public function testUnique(): void
    {
        $validator = Validator::make(
            ['name' => 'John Doe'],
            ['name' => 'required|unique:users'],
        );
        $this->assertFalse($validator->fails());

        User::create(['name' => 'John Doe']);

        $validator = Validator::make(
            ['name' => 'John Doe'],
            ['name' => 'required|unique:users'],
        );
        $this->assertTrue($validator->fails());

        $validator = Validator::make(
            ['name' => 'John doe'],
            ['name' => 'required|unique:users'],
        );
        $this->assertTrue($validator->fails());

        $validator = Validator::make(
            ['name' => 'john doe'],
            ['name' => 'required|unique:users'],
        );
        $this->assertTrue($validator->fails());

        $validator = Validator::make(
            ['name' => 'test doe'],
            ['name' => 'required|unique:users'],
        );
        $this->assertFalse($validator->fails());

        $validator = Validator::make(
            ['name' => 'John'], // Part of an existing value
            ['name' => 'required|unique:users'],
        );
        $this->assertFalse($validator->fails());

        User::create(['name' => 'Johnny Cash', 'email' => 'johnny.cash+200@gmail.com']);

        $validator = Validator::make(
            ['email' => 'johnny.cash+200@gmail.com'],
            ['email' => 'required|unique:users'],
        );
        $this->assertTrue($validator->fails());

        $validator = Validator::make(
            ['email' => 'johnny.cash+20@gmail.com'],
            ['email' => 'required|unique:users'],
        );
        $this->assertFalse($validator->fails());

        $validator = Validator::make(
            ['email' => 'johnny.cash+1@gmail.com'],
            ['email' => 'required|unique:users'],
        );
        $this->assertFalse($validator->fails());
    }

    public function testExists(): void
    {
        $validator = Validator::make(
            ['name' => 'John Doe'],
            ['name' => 'required|exists:users'],
        );
        $this->assertTrue($validator->fails());

        User::create(['name' => 'John Doe']);
        User::create(['name' => 'Test Name']);

        $validator = Validator::make(
            ['name' => 'John Doe'],
            ['name' => 'required|exists:users'],
        );
        $this->assertFalse($validator->fails());

        $validator = Validator::make(
            ['name' => 'john Doe'],
            ['name' => 'required|exists:users'],
        );
        $this->assertFalse($validator->fails());

        $validator = Validator::make(
            ['name' => ['test name', 'john doe']],
            ['name' => 'required|exists:users'],
        );
        $this->assertFalse($validator->fails());

        $validator = Validator::make(
            ['name' => ['test name', 'john']], // Part of an existing value
            ['name' => 'required|exists:users'],
        );
        $this->assertTrue($validator->fails());

        $validator = Validator::make(
            ['name' => '(invalid regex{'],
            ['name' => 'required|exists:users'],
        );
        $this->assertTrue($validator->fails());

        $validator = Validator::make(
            ['name' => ['foo', '(invalid regex{']],
            ['name' => 'required|exists:users'],
        );
        $this->assertTrue($validator->fails());

        User::create(['name' => '']);

        $validator = Validator::make(
            ['name' => []],
            ['name' => 'exists:users'],
        );
        $this->assertFalse($validator->fails());
    }

    public function testGetCountBuildsRegexWithValidFlags(): void
    {
        $query = new class {
            public $value;

            public function useWritePdo()
            {
                return $this;
            }

            public function where($column, $value)
            {
                $this->value = $value;

                return $this;
            }

            public function count()
            {
                return 0;
            }
        };

        $verifier = new class ($query) extends DatabasePresenceVerifier {
            public function __construct(private $query)
            {
            }

            protected function table($table)
            {
                return $this->query;
            }
        };

        $verifier->getCount('users', 'name', 'John Doe');

        $this->assertInstanceOf(Regex::class, $query->value);
        $this->assertSame('i', $query->value->getFlags());
    }
}
