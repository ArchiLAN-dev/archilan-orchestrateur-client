<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Tests\Sessions;

use Archilan\OrchestratorClient\Exception\NotFoundException;
use Archilan\OrchestratorClient\Exception\OrchestratorException;
use Archilan\OrchestratorClient\Exception\SessionNotFoundException;
use Archilan\OrchestratorClient\OrchestratorClient;
use Archilan\OrchestratorClient\Sessions\Response\SessionLogs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Story 17.33: the logs of a session's container.
 */
final class SessionLogsTest extends TestCase
{
    public function testLogs_asksForTheContainerAndTail(): void
    {
        $requested = '';
        $http = new MockHttpClient(function (string $method, string $url) use (&$requested): MockResponse {
            $requested = $method.' '.$url;

            return new MockResponse(json_encode(['container' => 'ap-server-s1', 'found' => true, 'logs' => "hosting\n"]) ?: '', ['http_code' => 200]);
        });

        $logs = (new OrchestratorClient('http://localhost:8000', 'key', $http))->sessions()->logs('s1', 'ap', 50);

        $this->assertSame('GET http://localhost:8000/sessions/s1/logs?container=ap&tail=50', $requested);
        $this->assertSame('ap-server-s1', $logs->container);
        $this->assertTrue($logs->found);
        $this->assertSame("hosting\n", $logs->logs);
    }

    public function testLogs_aMissingContainerIsFoundFalse(): void
    {
        $http = new MockHttpClient(new MockResponse('{"container":"archilan-gen-s1","found":false,"logs":""}', ['http_code' => 200]));

        $logs = (new OrchestratorClient('http://localhost:8000', 'key', $http))->sessions()->logs('s1', 'gen');

        $this->assertFalse($logs->found);
        $this->assertSame('', $logs->logs);
    }

    public function testLogs_anOrchestratorWithoutTheRouteSaysSo(): void
    {
        $http = new MockHttpClient(new MockResponse('404 page not found', ['http_code' => 404]));

        try {
            (new OrchestratorClient('http://localhost:8000', 'key', $http))->sessions()->logs('s1', 'ap');
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            self::assertNotInstanceOf(SessionNotFoundException::class, $e);
            self::assertStringContainsString('/logs', $e->getMessage());
        }
    }

    /** The type already refuses an unknown kind; a 400 the orchestrator still sends is an error. */
    public function testLogs_aRefusedRequestIsAnError(): void
    {
        $http = new MockHttpClient(new MockResponse('{"error":"invalid_container"}', ['http_code' => 400]));

        $this->expectException(OrchestratorException::class);
        $this->expectExceptionMessage('invalid_container');

        (new OrchestratorClient('http://localhost:8000', 'key', $http))->sessions()->logs('s1', 'bridge');
    }

    public function testFromArray_refusesAResponseWithoutContainer(): void
    {
        $this->expectException(OrchestratorException::class);
        SessionLogs::fromArray(['found' => true, 'logs' => 'x']);
    }
}
