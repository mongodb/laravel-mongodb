<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Ticket;

use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectID;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Query\Grammar;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Tests\TestCase;

use function array_key_exists;

/** @see https://github.com/mongodb/laravel-mongodb/issues/3549 */
class GH3549Test extends TestCase
{
    public function testCreatePreservesDistinctIntegerId(): void
    {
        $connection = DB::connection('mongodb');
        $originalGrammar = $connection->getQueryGrammar();
        $connection->setQueryGrammar(new GH3549Grammar($connection));
        $connection->getCollection('gh3549_requests')->drop();

        try {
            $request = GH3549Request::create([]);
            $created = $request->toArray();

            $fetched = GH3549Request::query()->find($request->id);
            $this->assertInstanceOf(GH3549Request::class, $fetched);
            $fetchedArray = $fetched->toArray();

            $this->assertSame(285028729, $created['id']);
            $this->assertSame(285028729, $created['cid']);
            $this->assertIsString($created['_id']);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{24}$/', $created['_id']);
            $this->assertSame($created['id'], $fetchedArray['id']);
            $this->assertSame($created['_id'], $fetchedArray['_id']);
            $this->assertSame($created['cid'], $fetchedArray['cid']);

            $stored = $connection->getCollection('gh3549_requests')->findOne(['id' => 285028729]);
            $this->assertIsObject($stored);
            $this->assertSame(285028729, $stored->id);
            $this->assertInstanceOf(ObjectID::class, $stored->_id);
            $this->assertSame($created['_id'], (string) $stored->_id);
        } finally {
            $connection->setQueryGrammar($originalGrammar);
            $connection->getCollection('gh3549_requests')->drop();
        }
    }

    public function testDefaultCreateStillUsesGeneratedId(): void
    {
        $user = User::create(['name' => 'Jane Poe']);

        $this->assertIsString($user->id);
        $this->assertSame($user->id, $user->_id);
        $this->assertArrayNotHasKey('_id', $user->toArray());
        $this->assertSame($user->id, $user->toArray()['id']);
    }
}

class GH3549Request extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'gh3549_requests';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (! $model->id) {
                $model->id = 285028729;
            }

            $model->cid = $model->id;
        });
    }
}

/**
 * Keeps a root `id` field instead of aliasing it to `_id`, which is what a
 * custom connection does when the primary key is not the MongoDB `_id`.
 */
class GH3549Grammar extends Grammar
{
    public function prepareFieldsForQuery(array $values, bool $root = true): array
    {
        $hasId = $root && array_key_exists('id', $values);
        $id = $hasId ? $values['id'] : null;

        if ($hasId) {
            unset($values['id']);
        }

        $values = parent::prepareFieldsForQuery($values, $root);

        if ($hasId) {
            $values['id'] = $id;
        }

        return $values;
    }
}
