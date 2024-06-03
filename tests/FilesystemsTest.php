<?php

namespace MongoDB\Laravel\Tests;

use Generator;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use League\Flysystem\UnableToWriteFile;
use MongoDB\GridFS\Bucket;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

use function env;
use function microtime;
use function stream_get_contents;

class FilesystemsTest extends TestCase
{
    public function tearDown(): void
    {
        $this->getBucket()->drop();

        parent::tearDown();
    }

    public static function provideValidOptions(): Generator
    {
        yield 'connection-minimal' => [
            [
                'driver' => 'gridfs',
                'connection' => 'mongodb',
            ],
        ];

        yield 'connection-full' => [
            [
                'driver' => 'gridfs',
                'connection' => 'mongodb',
                'database' => env('MONGODB_DATABASE', 'unittest'),
                'bucket' => 'fs',
                'prefix' => 'foo/',
            ],
        ];

        yield 'bucket-service' => [
            [
                'driver' => 'gridfs',
                'bucket' => 'bucket',
            ],
        ];

        yield 'bucket-factory' => [
            [
                'driver' => 'gridfs',
                'bucket' => static fn (Application $app) => $app['db']
                    ->connection('mongodb')
                    ->getMongoDB()
                    ->selectGridFSBucket(),
            ],
        ];
    }

    #[DataProvider('provideValidOptions')]
    public function testValidOptions(array $options)
    {
        // Service used by "bucket-service"
        $this->app->singleton('bucket', static fn (Application $app) => $app['db']
            ->connection('mongodb')
            ->getMongoDB()
            ->selectGridFSBucket());

        $this->app['config']->set('filesystems.disks.' . $this->dataName(), $options);

        $disk = Storage::disk($this->dataName());
        $disk->put($filename = $this->dataName() . '.txt', $value = microtime());
        $this->assertEquals($value, $disk->get($filename), 'File saved');
    }

    public static function provideInvalidOptions(): Generator
    {
        yield 'not-mongodb-connection' => [
            ['driver' => 'gridfs', 'connection' => 'sqlite'],
            'The database connection "sqlite" does not use the "mongodb" driver.',
        ];

        yield 'factory-not-bucket' => [
            ['driver' => 'gridfs', 'bucket' => static fn () => new stdClass()],
            'Unexpected value for GridFS "bucket" configuration. Expecting "MongoDB\GridFS\Bucket". Got "stdClass"',
        ];

        yield 'service-not-bucket' => [
            ['driver' => 'gridfs', 'bucket' => 'bucket'],
            'Unexpected value for GridFS "bucket" configuration. Expecting "MongoDB\GridFS\Bucket". Got "stdClass"',
        ];
    }

    #[DataProvider('provideInvalidOptions')]
    public function testInvalidOptions(array $options, string $message)
    {
        // Service used by "service-not-bucket"
        $this->app->singleton('bucket', static fn () => new stdClass());

        $this->app['config']->set('filesystems.disks.' . $this->dataName(), $options);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        Storage::disk($this->dataName());
    }

    public function testReadOnlyAndThrowOptions()
    {
        $this->app['config']->set('filesystems.disks.gridfs-readonly', [
            'driver' => 'gridfs',
            'connection' => 'mongodb',
            // Use ReadOnlyAdapter
            'read-only' => true,
            // Throw exceptions
            'throw' => true,
        ]);

        $disk = Storage::disk('gridfs-readonly');

        $this->expectException(UnableToWriteFile::class);
        $this->expectExceptionMessage('This is a readonly adapter.');

        $disk->put('file.txt', '');
    }

    public function testPrefix()
    {
        $this->app['config']->set('filesystems.disks.gridfs-prefix', [
            'driver' => 'gridfs',
            'connection' => 'mongodb',
            'prefix' => 'foo/bar/',
        ]);

        $disk = Storage::disk('gridfs-prefix');
        $disk->put('hello/world.txt', 'Hello World!');
        $this->assertSame('Hello World!', $disk->get('hello/world.txt'));
        $this->assertSame('Hello World!', stream_get_contents($this->getBucket()->openDownloadStreamByName('foo/bar/hello/world.txt')), 'File name is prefixed in the bucket');
    }

    private function getBucket(): Bucket
    {
        return DB::connection('mongodb')->getMongoDB()->selectGridFSBucket();
    }
}
