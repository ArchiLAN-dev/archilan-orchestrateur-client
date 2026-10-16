<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Tests\RequiredFiles;

use Archilan\OrchestratorClient\Exception\NotFoundException;
use Archilan\OrchestratorClient\OrchestratorClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Story 44.3: the files a world declares, for an apworld and for the image's own worlds.
 */
final class DeclaredFilesTest extends TestCase
{
    public function testAnApworldDeclaresItsFiles(): void
    {
        $requested = '';
        $http = new MockHttpClient(function (string $method, string $url) use (&$requested): MockResponse {
            $requested = $method.' '.$url;

            return new MockResponse(json_encode(['requiredFiles' => [
                ['setting' => 'DK3Settings.rom_file', 'file' => 'dkc3.sfc', 'extension' => '.sfc', 'description' => 'DKC3 (US) ROM File', 'md5s' => ['120ABF304F0C40FE059F6A192ED4F947']],
                ['file' => 'no-setting.sfc'],
            ]]) ?: '', ['http_code' => 200]);
        });

        $files = (new OrchestratorClient('http://localhost:8000', 'key', $http))->apworlds()->getDeclaredFiles('abc');

        $this->assertSame('GET http://localhost:8000/apworlds/abc/required-files', $requested);
        $this->assertCount(1, $files);
        $this->assertSame('DK3Settings.rom_file', $files[0]->setting);
        $this->assertSame('.sfc', $files[0]->extension);
        $this->assertSame('DKC3 (US) ROM File', $files[0]->description);
        $this->assertSame(['120abf304f0c40fe059f6a192ed4f947'], $files[0]->md5s);
    }

    public function testTheImageDeclaresTheFilesOfItsOwnWorlds(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode(['image' => 'sha256:a', 'games' => [
            'Ocarina of Time' => [['setting' => 'OOTSettings.rom_file', 'file' => 'oot.z64', 'extension' => '.z64']],
            'Empty' => [],
        ]]) ?: '', ['http_code' => 200]));

        $official = (new OrchestratorClient('http://localhost:8000', 'key', $http))->runtime()->getDeclaredFiles();

        $this->assertSame('sha256:a', $official->image);
        $this->assertSame(['Ocarina of Time'], array_keys($official->games));
        $this->assertNull($official->games['Ocarina of Time'][0]->description);
        $this->assertSame([], $official->games['Ocarina of Time'][0]->md5s);
    }

    public function testAnOrchestratorWithoutTheEndpointSaysSo(): void
    {
        $http = new MockHttpClient(new MockResponse('{"error":"not found"}', ['http_code' => 404]));

        $this->expectException(NotFoundException::class);
        (new OrchestratorClient('http://localhost:8000', 'key', $http))->runtime()->getDeclaredFiles();
    }
}
