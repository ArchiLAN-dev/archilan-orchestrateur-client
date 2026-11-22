<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Preflight\Response;

/**
 * Size of one slot's world and the progression it carries, read from a generation's multidata (story 56.1).
 */
final readonly class SlotMeasure
{
    public function __construct(
        public string $name,
        public string $game,
        public int $locations,
        public int $ownProgression,
        public int $hostedProgression,
    ) {
    }

    /** @return list<self> records missing a name or a count are skipped */
    public static function listFromArray(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }
        $measures = [];
        foreach ($data as $record) {
            if (!is_array($record)) {
                continue;
            }
            $name = $record['name'] ?? null;
            $locations = $record['locations'] ?? null;
            $own = $record['ownProgression'] ?? null;
            $hosted = $record['hostedProgression'] ?? null;
            if (!is_string($name) || '' === $name || !is_int($locations) || !is_int($own) || !is_int($hosted)) {
                continue;
            }
            $game = $record['game'] ?? null;
            $measures[] = new self($name, is_string($game) ? $game : '', $locations, $own, $hosted);
        }

        return $measures;
    }
}
