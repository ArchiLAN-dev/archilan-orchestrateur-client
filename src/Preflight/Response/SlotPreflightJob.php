<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Preflight\Response;

/**
 * State of a slot preflight generation job (story 9.42): the solo test generation of one
 * player's real YAML on the orchestrator. Jobs are in-memory on the orchestrator side and
 * expire; a poller receiving 404 must treat the verdict as unknown.
 */
final readonly class SlotPreflightJob
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PASSED = 'passed';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        public string $id,
        public string $status,
        public string $error = '',
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: is_string($data['id'] ?? null) ? $data['id'] : '',
            status: is_string($data['status'] ?? null) ? $data['status'] : '',
            error: is_string($data['error'] ?? null) ? $data['error'] : '',
        );
    }

    public function isSettled(): bool
    {
        return self::STATUS_PASSED === $this->status || self::STATUS_FAILED === $this->status;
    }
}
