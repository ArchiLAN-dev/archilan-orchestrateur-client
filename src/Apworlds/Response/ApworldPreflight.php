<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Apworlds\Response;

use Archilan\OrchestratorClient\Support\ResponseFields;

/**
 * Upload-time solo test-generation verdict for an apworld (story 9.38).
 *
 * Status is one of pending | passed | failed | skipped ("skipped" = no template YAML to
 * test with, the check could not run - treat as unknown, not as passed). Overridden is the
 * admin "force allow" escape hatch for a failed verdict. Image and imageId name the Archipelago
 * image the verdict was produced with (story 38.8). Both are null on a verdict older than that story,
 * on a pending verdict (its run has not produced anything yet) and on a skipped one (nothing ran);
 * imageId alone is null when the orchestrator could not inspect the image. Warning is what the generator
 * reported on a pass (story 38.12), e.g. an accessibility check not met that the official Launcher also lets
 * through: the apworld is usable, but its author has something to fix. Empty otherwise.
 */
final readonly class ApworldPreflight
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PASSED = 'passed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public function __construct(
        public string $status,
        public string $error = '',
        public string $checkedAt = '',
        public bool $overridden = false,
        public ?string $image = null,
        public ?string $imageId = null,
        public string $warning = '',
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            status: is_string($data['status'] ?? null) ? $data['status'] : '',
            error: is_string($data['error'] ?? null) ? $data['error'] : '',
            checkedAt: is_string($data['checkedAt'] ?? null) ? $data['checkedAt'] : '',
            overridden: true === ($data['overridden'] ?? null),
            image: ResponseFields::optionalString($data, 'image'),
            imageId: ResponseFields::optionalString($data, 'imageId'),
            warning: is_string($data['warning'] ?? null) ? $data['warning'] : '',
        );
    }

    /** A failed, non-overridden verdict is the only state that blocks using the apworld. */
    public function blocksUsage(): bool
    {
        return self::STATUS_FAILED === $this->status && !$this->overridden;
    }
}
