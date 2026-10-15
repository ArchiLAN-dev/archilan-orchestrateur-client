<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\RequiredFiles\Response;

use Archilan\OrchestratorClient\Exception\OrchestratorException;
use Archilan\OrchestratorClient\Support\ResponseFields;

/**
 * A file the orchestrator holds for one generation (story 44.2): the one-use id to name it with, and
 * when it is dropped if no generation took it.
 */
final readonly class StagedRequiredFile
{
    public function __construct(
        public string $stagedFileId,
        public \DateTimeImmutable $expiresAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws OrchestratorException when the response is incomplete
     */
    public static function fromArray(array $data): self
    {
        $id = ResponseFields::optionalString($data, 'stagedFileId')
            ?? throw new OrchestratorException("Missing or invalid field 'stagedFileId' in required file response");
        $expiresAt = ResponseFields::optionalString($data, 'expiresAt')
            ?? throw new OrchestratorException("Missing or invalid field 'expiresAt' in required file response");

        try {
            return new self($id, new \DateTimeImmutable($expiresAt));
        } catch (\Exception $e) {
            throw new OrchestratorException("Invalid field 'expiresAt' in required file response", 0, $e);
        }
    }
}
