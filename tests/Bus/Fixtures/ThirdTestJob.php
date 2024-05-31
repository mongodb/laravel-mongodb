<?php

namespace MongoDB\Laravel\Tests\Bus\Fixtures;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ThirdTestJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use Batchable;
}
