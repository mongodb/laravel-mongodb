<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

class UserWithEmbeds extends User
{
    protected $withEmbeds = ['addresses'];
}
