<?php

namespace MongoDB\Laravel\Tests\Scout;

use Laravel\Scout\Builder;
use MongoDB\Laravel\Tests\TestCase;

class AtlasSearchScoutTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Post::insert([
            ['title' => 'First Post', 'content' => 'First Post Content'],
            ['title' => 'Second Post', 'content' => 'Second Post Content'],
            ['title' => 'Third Post', 'content' => 'Third Post Content'],
        ]);
    }

    public function tearDown(): void
    {
        Post::truncate();

        parent::tearDown();
    }

    public function testGetScoutModelsByIds()
    {
        $post = Post::where('title', 'First Post')->first();

        $builder = $this->createMock(Builder::class);

        $posts = (new Post())->getScoutModelsByIds($builder, [
            (string) $post->id,
        ]);

        $this->assertCount(1, $posts);
        $this->assertSame($post->title, $posts->first()->title);
    }
}
