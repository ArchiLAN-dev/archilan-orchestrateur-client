<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Runtime;

use Archilan\OrchestratorClient\Exception\NotFoundException;
use Archilan\OrchestratorClient\Exception\OrchestratorException;
use Archilan\OrchestratorClient\Exception\SessionNotFoundException;
use Archilan\OrchestratorClient\Exception\ServiceUnavailableException;
use Archilan\OrchestratorClient\Http\HttpTransport;
use Archilan\OrchestratorClient\RequiredFiles\Response\OfficialDeclaredFiles;
use Archilan\OrchestratorClient\Runtime\Response\RuntimeInfo;

final class RuntimeClient
{
    public function __construct(private readonly HttpTransport $transport)
    {
    }

    /**
     * GET /runtime (story 38.8).
     *
     * @throws NotFoundException    when the orchestrator predates GET /runtime
     * @throws OrchestratorException when the response names no image
     */
    public function get(): RuntimeInfo
    {
        try {
            $data = $this->transport->getJson('/runtime');
        } catch (SessionNotFoundException $e) {
            // The transport reads every 404 as a missing session; here it is a missing endpoint.
            throw new NotFoundException('GET /runtime is not available on this orchestrator (older than story 38.8?)', 0, $e);
        }

        return RuntimeInfo::fromArray($data);
    }

    /**
     * GET /runtime/required-files (story 44.3): the files the image's own worlds declare.
     *
     * @throws NotFoundException             when the orchestrator predates story 44.3
     * @throws ServiceUnavailableException   when the image could not list them
     */
    public function getDeclaredFiles(): OfficialDeclaredFiles
    {
        try {
            $data = $this->transport->getJson('/runtime/required-files');
        } catch (SessionNotFoundException $e) {
            throw new NotFoundException('GET /runtime/required-files is not available on this orchestrator (older than story 44.3?)', 0, $e);
        }

        return OfficialDeclaredFiles::fromArray($data);
    }
}
