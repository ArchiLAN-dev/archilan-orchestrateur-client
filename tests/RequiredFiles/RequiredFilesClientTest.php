<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Tests\RequiredFiles;

use Archilan\OrchestratorClient\Exception\OrchestratorException;
use Archilan\OrchestratorClient\Exception\RequiredFileExpiredException;
use Archilan\OrchestratorClient\Exception\ServiceUnavailableException;
use Archilan\OrchestratorClient\OrchestratorClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Story 44.2: a file a world reads at generation, held by the orchestrator for one generation only.
 */
final class RequiredFilesClientTest extends TestCase
{
    private string $file = '';

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'rom');
        file_put_contents($this->file, 'ROMDATA');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testStage_streamsTheFileWithItsSetting(): void
    {
        $requested = '';
        $sent = '';
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$requested, &$sent): MockResponse {
            $requested = $method.' '.$url;
            $body = $options['body'] ?? '';
            if (is_callable($body)) {
                while ('' !== $chunk = $body(8192)) {
                    $sent .= $chunk;
                }
            } else {
                $sent = is_string($body) ? $body : '';
            }

            return new MockResponse(json_encode(['stagedFileId' => 'abc', 'expiresAt' => '2026-10-15T12:00:00Z']) ?: '', ['http_code' => 201]);
        });

        $staged = (new OrchestratorClient('http://localhost:8000', 'key', $http))
            ->requiredFiles()->stage('DK3Settings.rom_file', $this->file, 'dkc3.sfc');

        $this->assertSame('POST http://localhost:8000/required-files', $requested);
        $this->assertStringContainsString('name="setting"', $sent);
        $this->assertStringContainsString('DK3Settings.rom_file', $sent);
        $this->assertStringContainsString('filename="dkc3.sfc"', $sent);
        $this->assertStringContainsString('ROMDATA', $sent);
        $this->assertSame('abc', $staged->stagedFileId);
        $this->assertSame('2026-10-15T12:00:00+00:00', $staged->expiresAt->format(\DATE_ATOM));
    }

    public function testStage_aFullOrchestratorIsUnavailable(): void
    {
        $http = new MockHttpClient(new MockResponse('{"error":"required_files_full"}', ['http_code' => 503]));

        $this->expectException(ServiceUnavailableException::class);
        (new OrchestratorClient('http://localhost:8000', 'key', $http))->requiredFiles()->stage('DK3Settings.rom_file', $this->file, 'dkc3.sfc');
    }

    public function testStage_aRefusedFileIsAnError(): void
    {
        $http = new MockHttpClient(new MockResponse('{"error":"file too large"}', ['http_code' => 413]));

        $this->expectException(OrchestratorException::class);
        $this->expectExceptionMessage('HTTP 413');
        (new OrchestratorClient('http://localhost:8000', 'key', $http))->requiredFiles()->stage('DK3Settings.rom_file', $this->file, 'dkc3.sfc');
    }

    public function testRelease_deletesTheHeldFile(): void
    {
        $requested = '';
        $http = new MockHttpClient(function (string $method, string $url) use (&$requested): MockResponse {
            $requested = $method.' '.$url;

            return new MockResponse('', ['http_code' => 204]);
        });

        (new OrchestratorClient('http://localhost:8000', 'key', $http))->requiredFiles()->release('abc');

        $this->assertSame('DELETE http://localhost:8000/required-files/abc', $requested);
    }

    public function testGenerate_sendsTheHeldFileIds(): void
    {
        $body = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$body): MockResponse {
            $body = json_decode(is_string($options['body'] ?? null) ? $options['body'] : '{}', true);

            return new MockResponse('', ['http_code' => 202]);
        });

        (new OrchestratorClient('http://localhost:8000', 'key', $http))->sessions()->generate('s1', 'pw', null, [], ['abc', 'def']);

        $this->assertIsArray($body);
        $this->assertSame(['abc', 'def'], $body['requiredFileIds'] ?? null);
    }

    public function testGenerate_withoutFilesSendsNoIds(): void
    {
        $body = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$body): MockResponse {
            $body = json_decode(is_string($options['body'] ?? null) ? $options['body'] : '{}', true);

            return new MockResponse('', ['http_code' => 202]);
        });

        (new OrchestratorClient('http://localhost:8000', 'key', $http))->sessions()->generate('s1', 'pw');

        $this->assertIsArray($body);
        $this->assertArrayNotHasKey('requiredFileIds', $body);
    }

    public function testGenerate_anExpiredFileIsNamed(): void
    {
        $http = new MockHttpClient(new MockResponse('{"error":"required_file_expired"}', ['http_code' => 422]));

        $this->expectException(RequiredFileExpiredException::class);
        (new OrchestratorClient('http://localhost:8000', 'key', $http))->sessions()->generate('s1', 'pw', null, [], ['abc']);
    }
}
