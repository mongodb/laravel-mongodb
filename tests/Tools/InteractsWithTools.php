<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Shared helpers for MCP tool tests, providing the equivalents of Boost's Pest
 * tool expectations (toolHasNoError, toolJsonContent, toolTextContains, ...) so
 * tool tests don't have to re-implement running the tool and decoding its
 * response. Because these only rely on PHPUnit assertions, the same helpers
 * work for both integration and unit (mocked) tool tests, regardless of the
 * base TestCase.
 */
trait InteractsWithTools
{
    /**
     * Run a tool and return its response.
     *
     * @param array<string, mixed> $params
     */
    protected function runTool(Tool $tool, array $params = []): Response
    {
        return $tool->handle(new Request($params));
    }

    /**
     * Run a tool, assert it did not error, and return the decoded JSON body.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    protected function toolJson(Tool $tool, array $params = []): array
    {
        $response = $this->runTool($tool, $params);
        $this->assertToolHasNoError($response);

        return $this->decode($response);
    }

    /** @return array<string, mixed> */
    protected function decode(Response $response): array
    {
        return json_decode((string) $response->content(), true, flags: JSON_THROW_ON_ERROR);
    }

    protected function assertToolHasNoError(Response $response): void
    {
        $this->assertFalse($response->isError(), 'Expected the tool response to have no error.');
    }

    protected function assertToolHasError(Response $response): void
    {
        $this->assertTrue($response->isError(), 'Expected the tool response to be an error.');
    }

    protected function assertToolTextContains(Response $response, string $needle): void
    {
        $this->assertStringContainsString($needle, (string) $response->content());
    }
}
