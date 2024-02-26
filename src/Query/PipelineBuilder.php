<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Query;

use MongoDB\Builder\Stage\FluentFactory;

class PipelineBuilder extends FluentFactory
{
    public function __construct(array $pipeline = [])
    {
        $this->pipeline = $pipeline;
    }
}
