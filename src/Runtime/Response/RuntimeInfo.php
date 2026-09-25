<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Runtime\Response;

/**
 * The Archipelago image the orchestrator runs (story 38.8): its configured reference, and the id
 * of the local image it points to. The id is null when the orchestrator could not inspect it.
 */
final readonly class RuntimeInfo
{
    public function __construct(
        public string $apImage,
        public ?string $apImageId = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $id = $data['apImageId'] ?? null;

        return new self(
            apImage: is_string($data['apImage'] ?? null) ? $data['apImage'] : '',
            apImageId: is_string($id) && '' !== $id ? $id : null,
        );
    }
}
