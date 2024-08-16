<?php

namespace MongoDB\Laravel\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\ProviderRepository;
use MongoDB\Laravel\MongoDBServiceProvider;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;
use function tempnam;

class ServiceProviderTest extends TestCase
{
    public function testBusServiceProvider()
    {
        $app = new Application();
        $providerRepository = new ProviderRepository($app, new Filesystem(), tempnam(sys_get_temp_dir(), 'manifest') . '.php');
        $providerRepository->load([MongoDBServiceProvider::class]);
    }
}
