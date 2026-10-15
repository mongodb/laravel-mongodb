<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use MongoDB\Laravel\Tests\Models\EncryptedEmbedded;
use MongoDB\Laravel\Tests\Models\EncryptedEmbeddedItem;
use MongoDB\Laravel\Tests\TestCase;

/**
 * An embedded document is stored inside the document of its parent, so the
 * encrypted fields of its own attributes are declared as dotted paths in the
 * encryptedFieldsMap of the parent collection, and are coerced when the
 * embedded document is written through its relation.
 *
 * These tests do not need a server: only the connection configuration is read.
 */
final class EncryptedEmbeddedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The map below is read by resolving the connection, which builds the
        // auto encryption options and raises on an extension that cannot
        // resolve a keyAltName.
        $this->skipIfKeyAltNameIsNotSupported();

        $this->enableEncryption([
            'encrypted_embedded' => [
                'fields' => [
                    'name' => 'string',
                    // EmbedsOne relation.
                    'address.amount' => 'int',
                    // EmbedsOne relation nested in "address".
                    'address.label.amount' => 'int',
                ],
            ],
        ]);
    }

    public function testCoercesAnEmbedsOneRelation(): void
    {
        $parent = new EncryptedEmbedded(['name' => 'Jane']);

        $parent->address()->associate(new EncryptedEmbeddedItem(['amount' => '1500']));

        self::assertSame(1500, $parent->getAttributes()['address']['amount']);
    }

    public function testCoercesANestedEmbedsOneRelation(): void
    {
        $parent = new EncryptedEmbedded(['name' => 'Jane']);
        $item = new EncryptedEmbeddedItem();
        // The relation path is built from the parent relation of the model.
        $item->setParentRelation($parent->address());

        $item->label()->associate(new EncryptedEmbeddedItem(['amount' => '1500']));

        self::assertSame(1500, $item->getAttributes()['label']['amount']);
    }

    public function testCoercesTheSubFieldsOfAnEmbeddedDocumentAssignedOnTheParent(): void
    {
        $parent = new EncryptedEmbedded();
        $parent->address = ['amount' => '1500', 'label' => ['amount' => '1600']];

        self::assertSame(1500, $parent->getAttributes()['address']['amount']);
        self::assertSame(1600, $parent->getAttributes()['address']['label']['amount']);
    }

    public function testACustomCastOfAnEmbeddedModelOwnsItsValue(): void
    {
        // As on the parent model, a custom cast owns its value: the coercion
        // must not change what the cast returned.
        $item = new EncryptedEmbeddedItem();
        $item->mergeCasts(['amount' => RecordingCast::class]);
        $item->amount = '1500';

        $parent = new EncryptedEmbedded(['name' => 'Jane']);
        $parent->address()->associate($item);

        self::assertSame('1500', $parent->getAttributes()['address']['amount']);
    }

    public function testACustomCastOfANestedEmbeddedModelOwnsItsValue(): void
    {
        // The nested relation coerces its own model, which honors the custom
        // cast. Associating the outer model must not coerce that value again.
        $parent = new EncryptedEmbedded(['name' => 'Jane']);

        $item = new EncryptedEmbeddedItem();
        $item->setParentRelation($parent->address());

        $label = new EncryptedEmbeddedItem();
        $label->mergeCasts(['amount' => RecordingCast::class]);
        $label->amount = '1500';
        $item->label()->associate($label);

        $parent->address()->associate($item);

        self::assertSame('1500', $parent->getAttributes()['address']['label']['amount']);
    }

    public function testValuesThatCannotBeConvertedAreLeftUntouched(): void
    {
        $parent = new EncryptedEmbedded();
        $parent->address = ['amount' => 'abc', 'note' => '42'];

        // A value that cannot be converted is passed to the driver as is, and a
        // sub-field that is not declared is left untouched.
        self::assertSame('abc', $parent->getAttributes()['address']['amount']);
        self::assertSame('42', $parent->getAttributes()['address']['note']);
    }

    public function testNoCoercionWhenTheCollectionIsNotEncrypted(): void
    {
        $this->enableEncryption([]);

        $parent = new EncryptedEmbedded();
        $parent->address = ['amount' => '1500'];

        self::assertSame('1500', $parent->getAttributes()['address']['amount']);
    }
}
