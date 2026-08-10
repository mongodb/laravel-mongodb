<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Ticket;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\MorphOne as MongoMorphOne;
use MongoDB\Laravel\Relations\MorphTo;
use MongoDB\Laravel\Tests\TestCase;

/**
 * @see https://github.com/mongodb/laravel-mongodb/issues/2783
 * @see https://jira.mongodb.org/browse/PHPLARA-198
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

    public function testMorphOneUsesMongoRelationClass()
    {
        $post = new GH2783Post();

        $this->assertInstanceOf(MongoMorphOne::class, $post->image());
    }

    public function testMorphOneEagerLoadWithIntegerKeyType()
    {
        // Only reproduces with an integer $keyType: Relation::whereInMethod() resolves to whereIntegerInRaw(),
        // when eager loading multiple parents whose primary key is an integer and MongoDB\Laravel\Query\Builder does not support that method.
        // String keys (the default for Mongo models) already resolve to whereIn() even with the native class.
        GH2783IntKeyImage::truncate();
        GH2783IntKeyPost::truncate();

        $post1 = GH2783IntKeyPost::create(['_id' => 1, 'text' => 'Lorem ipsum']);
        $post2 = GH2783IntKeyPost::create(['_id' => 2, 'text' => 'Dolor sit amet']);

        $image1 = GH2783IntKeyImage::create(['uri' => 'http://example.com/1.png']);
        $image1->imageable()->associate($post1)->save();

        $image2 = GH2783IntKeyImage::create(['uri' => 'http://example.com/2.png']);
        $image2->imageable()->associate($post2)->save();

        $posts = GH2783IntKeyPost::with('image')->get();

        $this->assertCount(2, $posts);
        foreach ($posts as $post) {
            $this->assertInstanceOf(GH2783IntKeyImage::class, $post->image);
        }
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

class GH2783IntKeyImage extends Model
{
    protected $connection = 'mongodb';
    protected $fillable = ['uri'];

    public function imageable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'imageable_type', 'imageable_id');
    }
}

class GH2783IntKeyPost extends Model
{
    protected $connection = 'mongodb';
    protected $fillable = ['_id', 'text'];
    protected $keyType = 'int';
    public $incrementing = false;

    public function image(): MorphOne
    {
        return $this->morphOne(GH2783IntKeyImage::class, 'imageable');
    }
}
