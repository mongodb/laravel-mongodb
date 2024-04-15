<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use MongoDB\BSON\ObjectId;
use MongoDB\Client as MongoDBClient;
use MongoDB\Driver\Manager;
use MongoDB\GridFS\Bucket;
use MongoDB\Laravel\Tests\TestCase;

use function fclose;
use function file_get_contents;
use function fopen;
use function fwrite;
use function stream_get_contents;

class GridFSTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testBucketManager(): void
    {
            $manager = new Manager('mongodb://localhost:27017');
            $bucket = new Bucket(
                $manager,
                'myGridFSDb',
                [
                    'bucketName' => 'grid',
                    'chunkSizeBytes' => 1048576,
                ],
            );

            $metadata = ['description' => 'A very important message.'];
            $result = $bucket->uploadFromStream(
                'example.dat',
                fopen('php://memory', 'r+'),
                ['metadata' => $metadata],
            );

            echo $result; // Outputs MongoDB\BSON\ObjectId
            $this->assertNotNull($result);
    }

    public function testSelectBucket(): void
    {
            $db = (new MongoDBClient())->filesDb;
            $bucket = $db->selectGridFSBucket([
                'bucketName' => 'grid',
                'chunkSizeBytes' => 1048576,
            ]);

            $metadata = ['description' => 'A very important message.'];
            $result = $bucket->uploadFromStream(
                'example.dat',
                fopen('php://memory', 'r+'),
                ['metadata' => $metadata],
            );

            echo $result; // Outputs MongoDB\BSON\ObjectId
    }

    public function testOpenUploadStream(): void
    {
            $db = (new MongoDBClient())->filesDb;
            $bucket = $db->selectGridFSBucket([
                'bucketName' => 'grid',
                'chunkSizeBytes' => 1048576,
            ]);

            $stream = $bucket->openUploadStream('new-file.txt');
            $contents = file_get_contents('docs/includes/fundamentals/gridfs/example.dat');
            fwrite($stream, $contents);
            fclose($stream);
    }

    public function testUploadStreamAtOnce(): void
    {
            $db = (new MongoDBClient())->filesDb;
            $bucket = $db->selectGridFSBucket([
                'bucketName' => 'grid',
                'chunkSizeBytes' => 1048576,
            ]);

            $file = fopen('docs/includes/fundamentals/gridfs/example.dat', 'rb');
            $result = $bucket->uploadFromStream(
                'new-file.txt',
                $file,
            );

            echo $result; // Outputs MongoDB\BSON\ObjectId
    }

    private function uploadFileHelper(): ObjectId
    {
        $db = (new MongoDBClient())->filesDb;
        $bucket = $db->selectGridFSBucket([
            'bucketName' => 'grid',
            'chunkSizeBytes' => 1048576,
        ]);

        $file = fopen('docs/includes/fundamentals/gridfs/example.dat', 'rb');
        return $bucket->uploadFromStream(
            'new-file.txt',
            $file,
        );
    }

    public function testDownloadStream(): void
    {
        $fileId = $this->uploadFileHelper();

        $bucket = (new MongoDBClient())->filesDb->selectGridFSBucket(['bucketName' => 'grid']);
        $stream = $bucket->openDownloadStream($fileId);
        $contents = stream_get_contents($stream);

        echo $contents;
    }

    public function testDownloadFile(): void
    {
        $fileId = $this->uploadFileHelper();

        $bucket = (new MongoDBClient())->filesDb->selectGridFSBucket(['bucketName' => 'grid']);

        $file = fopen('docs/includes/fundamentals/gridfs/download.txt', 'wb');
        $bucket->downloadToStream($fileId, $file);
    }
}
