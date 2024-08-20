<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Ticket;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\MorphTo;
use MongoDB\Laravel\Tests\TestCase;

/**
 * @see https://github.com/mongodb/laravel-mongodb/issues/2783
 * @see https://jira.mongodb.org/browse/PHPORM-175
 */
class GH2783Test extends TestCase
{
    public function testMorphToInfersCustomOwnerKey()
    {
        GH2783Image::truncate();
        GH2783Post::truncate();
        GH2783User::truncate();

        $post = GH2783Post::create(['text' => 'Lorem ipsum']);
        $user = GH2783User::create(['username' => 'jsmith']);

        $imageWithPost = GH2783Image::create(['uri' => 'http://example.com/post.png']);
        $imageWithPost->imageable()->associate($post)->save();

        $imageWithUser = GH2783Image::create(['uri' => 'http://example.com/user.png']);
        $imageWithUser->imageable()->associate($user)->save();

        $queriedImageWithPost = GH2783Image::with('imageable')->find($imageWithPost->getKey());
        $this->assertInstanceOf(GH2783Post::class, $queriedImageWithPost->imageable);
        $this->assertEquals($post->id, $queriedImageWithPost->imageable->getKey());

        $queriedImageWithUser = GH2783Image::with('imageable')->find($imageWithUser->getKey());
        $this->assertInstanceOf(GH2783User::class, $queriedImageWithUser->imageable);
        $this->assertEquals($user->username, $queriedImageWithUser->imageable->getKey());
    }
}

class GH2783Image extends Model
{
    protected $connection = 'mongodb';
    protected $fillable = ['uri'];

    public function imageable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'imageable_type', 'imageable_id');
    }
}

class GH2783Post extends Model
{
    protected $connection = 'mongodb';
    protected $fillable = ['text'];

    public function image(): MorphOne
    {
        return $this->morphOne(GH2783Image::class, 'imageable');
    }
}

class GH2783User extends Model
{
    protected $connection = 'mongodb';
    protected $fillable = ['username'];
    protected $primaryKey = 'username';

    public function image(): MorphOne
    {
        return $this->morphOne(GH2783Image::class, 'imageable');
    }
}
