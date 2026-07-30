<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Tests\Preflight;

use Archilan\OrchestratorClient\Exception\OrchestratorException;
use Archilan\OrchestratorClient\Http\HttpTransport;
use Archilan\OrchestratorClient\Preflight\PreflightClient;
use Archilan\OrchestratorClient\Preflight\Response\SlotPreflightJob;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PreflightClientTest extends TestCase
{
    private function client(MockResponse $response): PreflightClient
    {
        $transport = new HttpTransport(new MockHttpClient($response), 'http://localhost:8000', 'key');

        return new PreflightClient($transport);
    }

    public function testStart_returnsPendingJob(): void
    {
        $body = json_encode(['id' => 'job-1', 'status' => 'pending']) ?: '';
        $client = $this->client(new MockResponse($body, ['http_code' => 202]));

        $job = $client->start("name: Jean\ngame: TUNIC\n", 'deadbeef');

        $this->assertSame('job-1', $job->id);
        $this->assertSame(SlotPreflightJob::STATUS_PENDING, $job->status);
        $this->assertFalse($job->isSettled());
    }

    public function testStart_missingIdThrows(): void
    {
        $body = json_encode(['status' => 'pending']) ?: '';
        $client = $this->client(new MockResponse($body, ['http_code' => 202]));

        $this->expectException(OrchestratorException::class);
        $client->start("name: Jean\ngame: TUNIC\n");
    }

    public function testGet_settledFailedJobCarriesError(): void
    {
        $body = json_encode(['id' => 'job-1', 'status' => 'failed', 'error' => 'Exception: boom']) ?: '';
        $client = $this->client(new MockResponse($body, ['http_code' => 200]));

        $job = $client->get('job-1');

        $this->assertTrue($job->isSettled());
        $this->assertSame('Exception: boom', $job->error);
    }

    public function testGet_passedJobIsSettled(): void
    {
        $body = json_encode(['id' => 'job-1', 'status' => 'passed']) ?: '';
        $client = $this->client(new MockResponse($body, ['http_code' => 200]));

        $this->assertTrue($client->get('job-1')->isSettled());
    }
}
