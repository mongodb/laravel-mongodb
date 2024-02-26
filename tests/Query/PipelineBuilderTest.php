<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Query;

use MongoDB\BSON\Document;
use MongoDB\Builder\BuilderEncoder;
use MongoDB\Laravel\Query\PipelineBuilder;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Tests\TestCase;

use function assert;

class PipelineBuilderTest extends TestCase
{
    public function testCreateFromQueryBuilder(): void
    {
        $builder = User::where('foo', 'bar')->aggregate();
        assert($builder instanceof PipelineBuilder);
        $builder->addFields(baz: 'qux');

        $codec = new BuilderEncoder();
        $pipeline = $codec->encode($builder->getPipeline());
        $json = Document::fromPHP(['pipeline' => $pipeline])->toCanonicalExtendedJSON();

        $expected = Document::fromPHP([
            'pipeline' => [
                [
                    '$match' => ['foo' => 'bar'],
                ],
                [
                    '$addFields' => ['baz' => 'qux'],
                ],
            ],
        ])->toCanonicalExtendedJSON();

        $this->assertJsonStringEqualsJsonString($expected, $json);
    }
}
