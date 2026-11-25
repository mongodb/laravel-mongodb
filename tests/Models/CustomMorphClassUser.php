<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Override;

/** A user that declares its morph class without registering a morph map. */
class CustomMorphClassUser extends User
{
    protected $table = 'users';

    #[Override]
    public function getMorphClass(): string
    {
        return 'custom-morph-class-user';
    }
}
