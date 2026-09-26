<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Runtime\Response;

use Archilan\OrchestratorClient\Exception\OrchestratorException;
use Archilan\OrchestratorClient\Support\ResponseFields;

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

    /**
     * @param array<string, mixed> $data
     *
     * @throws OrchestratorException when the response names no image
     */
    public static function fromArray(array $data): self
    {
        $apImage = ResponseFields::optionalString($data, 'apImage')
            ?? throw new OrchestratorException("Missing or invalid field 'apImage' in runtime response");

        return new self($apImage, ResponseFields::optionalString($data, 'apImageId'));
    }
}
