<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Preflight;

use Archilan\OrchestratorClient\Exception\OrchestratorException;
use Archilan\OrchestratorClient\Http\HttpTransport;
use Archilan\OrchestratorClient\Preflight\Response\SlotPreflightJob;

/**
 * Slot preflight generations (story 9.42): queue a solo test generation of one player's
 * real YAML and poll the job until it settles.
 */
final class PreflightClient
{
    public function __construct(private readonly HttpTransport $transport)
    {
    }

    /**
     * Queue a solo test generation. $apworldHash is null for official worlds bundled in
     * the generation image. Returns the job in "pending" state.
     */
    public function start(string $playerYaml, ?string $apworldHash = null): SlotPreflightJob
    {
        $body = ['playerYaml' => $playerYaml];
        if (null !== $apworldHash && '' !== $apworldHash) {
            $body['apworldHash'] = $apworldHash;
        }

        $job = SlotPreflightJob::fromArray($this->transport->postJson('/preflight-generations', $body));
        if ('' === $job->id) {
            throw new OrchestratorException('Missing job id in preflight-generations response');
        }

        return $job;
    }

    /** Poll a job. Throws OrchestratorException on HTTP errors, including expired ids (404). */
    public function get(string $jobId): SlotPreflightJob
    {
        return SlotPreflightJob::fromArray($this->transport->getJson("/preflight-generations/{$jobId}"));
    }
}
