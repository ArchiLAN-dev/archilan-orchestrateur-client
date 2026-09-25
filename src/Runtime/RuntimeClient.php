<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Runtime;

use Archilan\OrchestratorClient\Exception\OrchestratorException;
use Archilan\OrchestratorClient\Http\HttpTransport;
use Archilan\OrchestratorClient\Runtime\Response\RuntimeInfo;

final class RuntimeClient
{
    public function __construct(private readonly HttpTransport $transport)
    {
    }

    /**
     * GET /runtime (story 38.8).
     *
     * @throws OrchestratorException when the response names no image
     */
    public function get(): RuntimeInfo
    {
        $runtime = RuntimeInfo::fromArray($this->transport->getJson('/runtime'));
        if ('' === $runtime->apImage) {
            throw new OrchestratorException('Missing apImage in runtime response');
        }

        return $runtime;
    }
}
