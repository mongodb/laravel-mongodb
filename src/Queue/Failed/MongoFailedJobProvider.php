<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Queue\Failed;

use Illuminate\Queue\Failed\DatabaseFailedJobProvider;

/** @deprecated Use {@see DatabaseFailedJobProvider} */
class MongoFailedJobProvider extends DatabaseFailedJobProvider
{
}
