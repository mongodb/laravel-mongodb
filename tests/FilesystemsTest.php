<?php

namespace MongoDB\Laravel\Tests;

use Generator;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\Attributes\DataProvider;

use function env;
use function microtime;

class FilesystemsTest extends TestCase
{
    public function tearDown(): void
    {
        DB::connection('mongodb')->getMongoDB()->drop();

        parent::tearDown();
    }

    public static function provideDiskConfigurations(): Generator
    {
        yield [
            'gridfs-connection-minimal',
            [
                'driver' => 'gridfs',
                'connection' => 'mongodb',
            ],
        ];

        yield [
            'gridfs-connection-full',
            [
                'driver' => 'gridfs',
                'connection' => 'mongodb',
                'database' => env('MONGODB_DATABASE', 'unittest'),
                'bucket' => 'fs',
                'prefix' => 'foo/',
            ],
        ];

        yield [
            'gridfs-bucket-service',
            [
                'driver' => 'gridfs',
                'bucket' => 'bucket',
            ],
        ];

        yield [
            'gridfs-bucket-factory',
            [
                'driver' => 'gridfs',
                'bucket' => static fn (Application $app) => $app['db']
                    ->connection('mongodb')
                    ->getMongoDB()
                    ->selectGridFSBucket(),
            ],
        ];
    }

    #[DataProvider('provideDiskConfigurations')]
    public function testConfig(string $name, array $options)
    {
        // Service used by "gridfs-bucket-service"
        $this->app->singleton('bucket', static fn (Application $app) => $app['db']
            ->connection('mongodb')
            ->getMongoDB()
            ->selectGridFSBucket());

        $this->app['config']->set('filesystems.disks.' . $name, $options);

        $disk = Storage::disk($name);
        $disk->put($filename = $name . '.txt', $value = microtime());
        $this->assertEquals($value, $disk->get($filename), 'File saved');
    }

    public function testReadOnlyAndThrowOption()
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
}
