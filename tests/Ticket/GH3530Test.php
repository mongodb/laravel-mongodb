<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Ticket;

use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Tests\TestCase;

/** @see https://github.com/mongodb/laravel-mongodb/issues/3530 */
class GH3530Test extends TestCase
{
    public function tearDown(): void
    {
        DB::connection('mongodb')->disableQueryLog();
        DB::table('gh3530_items')->truncate();

        parent::tearDown();
    }

    public function testQueryLogIsMongoshSyntax(): void
    {
        DB::table('gh3530_items')->truncate();
        DB::enableQueryLog();

        DB::table('gh3530_items')->where('name', 'test')->get();
        DB::table('gh3530_items')->insert(['name' => 'test']);
        DB::table('gh3530_items')->where('name', 'test')->update(['name' => 'changed']);
        DB::table('gh3530_items')->where('name', 'changed')->count();
        DB::table('gh3530_items')->where('name', 'changed')->delete();

        $logs = DB::getQueryLog();

        self::assertSame('db.getCollection("gh3530_items").find({name: "test"})', $logs[0]['query']);
        self::assertMatchesRegularExpression(
            '/^db\.getCollection\("gh3530_items"\)\.insertOne\(\{name: "test", _id: ObjectId\("[a-f0-9]{24}"\)\}\)$/',
            $logs[1]['query'],
        );
        self::assertSame(
            'db.getCollection("gh3530_items").updateMany({name: "test"}, {$set: {name: "changed"}})',
            $logs[2]['query'],
        );
        self::assertSame('db.getCollection("gh3530_items").countDocuments({name: "changed"})', $logs[3]['query']);
        self::assertSame('db.getCollection("gh3530_items").deleteMany({name: "changed"})', $logs[4]['query']);

        foreach ($logs as $log) {
            self::assertStringNotContainsString('$oid', $log['query']);
            self::assertStringNotContainsString('$numberInt', $log['query']);
        }
    }
}
