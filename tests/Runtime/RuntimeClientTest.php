<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Tests\Runtime;

use Archilan\OrchestratorClient\Exception\OrchestratorException;
use Archilan\OrchestratorClient\OrchestratorClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Story 38.8: the Archipelago image the orchestrator runs.
 */
final class RuntimeClientTest extends TestCase
{
    public function testGet_parsesTheResponse(): void
    {
        $requested = '';
        $http = new MockHttpClient(function (string $method, string $url) use (&$requested): MockResponse {
            $requested = $method.' '.$url;

            return new MockResponse(json_encode(['apImage' => 'ghcr.io/archilan-dev/archipelago:0.16.1', 'apImageId' => 'sha256:abc123']) ?: '', ['http_code' => 200]);
        });

        $runtime = (new OrchestratorClient('http://localhost:8000', 'key', $http))->runtime()->get();

        $this->assertSame('GET http://localhost:8000/runtime', $requested);
        $this->assertSame('ghcr.io/archilan-dev/archipelago:0.16.1', $runtime->apImage);
        $this->assertSame('sha256:abc123', $runtime->apImageId);
    }

    public function testGet_anImageThatCouldNotBeInspectedHasNoId(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode(['apImage' => 'archipelago:latest', 'apImageId' => '']) ?: '', ['http_code' => 200]));

        $runtime = (new OrchestratorClient('http://localhost:8000', 'key', $http))->runtime()->get();

        $this->assertSame('archipelago:latest', $runtime->apImage);
        $this->assertNull($runtime->apImageId);
    }

    public function testGet_aResponseWithoutImageIsAnError(): void
    {
        $http = new MockHttpClient(new MockResponse('{}', ['http_code' => 200]));

        $this->expectException(OrchestratorException::class);
        (new OrchestratorClient('http://localhost:8000', 'key', $http))->runtime()->get();
    }
}
