<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\RequiredFiles\Response;

use Archilan\OrchestratorClient\Support\ResponseFields;

/**
 * By game name, the files the worlds shipped with the Archipelago image declare (story 44.3), and the
 * id of that image (null when it could not be inspected).
 */
final readonly class OfficialDeclaredFiles
{
    /** @param array<string, list<DeclaredFile>> $games */
    public function __construct(
        public ?string $image,
        public array $games,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $games = [];
        $raw = $data['games'] ?? null;
        foreach (is_array($raw) ? $raw : [] as $game => $files) {
            if (is_string($game) && [] !== ($declared = DeclaredFile::listFrom($files))) {
                $games[$game] = $declared;
            }
        }

        return new self(ResponseFields::optionalString($data, 'image'), $games);
    }
}
