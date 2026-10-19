<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Apworlds\Response;

/**
 * The static item names and item groups of an apworld (story 9.58), introspected from its World class:
 * item_name_to_id keys and item_name_groups, without Archipelago's implicit "Everything" group.
 *
 * A static hint, like the locations: items a world creates from its options are not listed. Empty until the
 * apworld has been introspected by an image that knows items.
 */
final readonly class ApworldItems
{
    /**
     * @param list<string>               $items
     * @param array<string, list<string>> $groups group name => its item names
     */
    public function __construct(
        public array $items = [],
        public array $groups = [],
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $groups = [];
        $rawGroups = $data['itemGroups'] ?? null;
        foreach (is_array($rawGroups) ? $rawGroups : [] as $name => $members) {
            $items = self::names($members);
            if (is_string($name) && '' !== $name && [] !== $items) {
                $groups[$name] = $items;
            }
        }

        return new self(self::names($data['items'] ?? null), $groups);
    }

    /**
     * @return list<string>
     */
    private static function names(mixed $raw): array
    {
        $names = [];
        foreach (is_array($raw) ? $raw : [] as $name) {
            if (is_string($name) && '' !== $name) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
