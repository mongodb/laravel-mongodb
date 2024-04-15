<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use MongoDB\BSON\ObjectId;
use MongoDB\Client as MongoDBClient;
use MongoDB\Driver\Exception\Exception;
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
        try {
            $manager = new MongoDB\Driver\Manager('mongodb://localhost:27017');
            $bucket = new MongoDB\GridFS\Bucket(
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
        } catch (Exception $e) {
            echo $e;
        }
    }

    public function testSelectBucket(): void
    {
        try {
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
        } catch (Exception $e) {
            echo $e;
        }
    }

    public function testOpenUploadStream(): void
    {
        try {
            $db = (new MongoDBClient())->filesDb;
            $bucket = $db->selectGridFSBucket([
                'bucketName' => 'grid',
                'chunkSizeBytes' => 1048576,
            ]);

            $stream = $bucket->openUploadStream('new-file.txt');
            $contents = file_get_contents('docs/includes/fundamentals/gridfs/example.dat');
            fwrite($stream, $contents);
            fclose($stream);
        } catch (Exception $e) {
            echo $e;
        }
    }

    public function testUploadStreamAtOnce(): void
    {
        try {
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
        } catch (Exception $e) {
            echo $e;
        }
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
