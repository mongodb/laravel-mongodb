<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Tools;

use Generator;
use Illuminate\Foundation\Application;
use Laravel\Boost\BoostServiceProvider;
use Laravel\Boost\Mcp\ToolRegistry;
use Laravel\Mcp\Server\Tool;
use MongoDB\Laravel\Tests\TestCase;
use MongoDB\Laravel\Tools\DatabaseInfo;
use MongoDB\Laravel\Tools\DatabaseQuery;

use function array_merge;

/**
 * Verifies that tools are registered and run without errors given happy-path parameters.
 */
class ToolsIntegrationTest extends TestCase
{
    use InteractsWithTools;

    /**
     * Load Boost alongside our provider so registerBoostTools() runs.
     *
     * @param  Application $app
     */
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [BoostServiceProvider::class]);
    }

    public function testTools()
    {
        foreach ($this->tools() as [$tool, $params]) {
            $this->assertContains($tool::class, ToolRegistry::getAvailableTools());

            $response = $this->runTool($tool, $params);
            $this->assertToolHasNoError($response);
        }
    }

    /** @return Generator<int, array{0: Tool, 1: array<string, mixed>}> */
    private function tools(): Generator
    {
        yield [new DatabaseInfo(), ['connection' => 'mongodb']];
        yield [new DatabaseQuery(), ['connection' => 'mongodb', 'command' => ['find' => 'examples']]];
    }
}
