<?php

namespace MongoDB\Laravel\Tests\Eloquent;

use Generator;
use Illuminate\Database\Eloquent\Model as BaseModel;
use MongoDB\Laravel\Auth\User;
use MongoDB\Laravel\Eloquent\DocumentModel;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Tests\Models\Book;
use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\Models\SqlBook;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ModelTest extends TestCase
{
    #[DataProvider('provideDocumentModelClasses')]
    public function testIsDocumentModel(bool $expected, string|object $classOrObject): void
    {
        $this->assertSame($expected, Model::isDocumentModel($classOrObject));
    }

    public static function provideDocumentModelClasses(): Generator
    {
        // Test classes
        yield [false, SqlBook::class];
        yield [true, Casting::class];
        yield [true, Book::class];

        // Provided by the Laravel MongoDB package.
        yield [true, User::class];

        // Instances of objects
        yield [false, new SqlBook()];
        yield [true, new Book()];

        // Anonymous classes
        yield [
            true,
            new class extends Model {
            },
        ];
        yield [
            true,
            new class extends BaseModel {
                use DocumentModel;
            },
        ];
        yield [
            false,
            new class {
                use DocumentModel;
            },
        ];
        yield [
            false,
            new class extends BaseModel {
            },
        ];
    }
}
