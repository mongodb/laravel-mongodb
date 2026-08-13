<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

#[UseModel(AttributedDocument::class)]
class AttributedDocumentFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'Bilbo'];
    }
}
