<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests;

use Illuminate\Queue\SerializesModels;

class EmbeddedRelationsJob
{
    use SerializesModels;

    public function __construct(public $users)
    {
    }
}
