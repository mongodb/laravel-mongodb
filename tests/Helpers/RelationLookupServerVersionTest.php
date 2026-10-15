<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Helpers;

use Illuminate\Database\Eloquent\Relations\Relation;
use LogicException;
use MongoDB\Laravel\Helpers\RelationLookup;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Tests\TestCase;

class RelationLookupServerVersionTest extends TestCase
{
    public function testAServerOlderThanMongoDB5IsRejected(): void
    {
        if ($this->getConnection('mongodb')->supportsRelationLookup()) {
            self::markTestSkipped('The server supports relationship lookups.');
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Relationship lookups require MongoDB 5.0 or later, and the server of connection [mongodb] is older.');

        RelationLookup::for(Relation::noConstraints(fn () => (new User())->books()), '__rel');
    }
}
