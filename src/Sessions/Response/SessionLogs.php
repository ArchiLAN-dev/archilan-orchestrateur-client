<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Sessions\Response;

use Archilan\OrchestratorClient\Exception\OrchestratorException;
use Archilan\OrchestratorClient\Support\ResponseFields;

/**
 * The last lines of one container of a session (story 17.33): stdout and stderr in order, each line
 * prefixed with Docker's timestamp. found is false when the container does not exist (never
 * launched, or removed since); logs is then empty.
 */
final readonly class SessionLogs
{
    public function __construct(
        public string $container,
        public bool $found,
        public string $logs,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws OrchestratorException when the response names no container
     */
    public static function fromArray(array $data): self
    {
        $container = ResponseFields::optionalString($data, 'container')
            ?? throw new OrchestratorException("Missing or invalid field 'container' in logs response");
        $logs = $data['logs'] ?? '';

        return new self($container, true === ($data['found'] ?? false), is_string($logs) ? $logs : '');
    }
}
