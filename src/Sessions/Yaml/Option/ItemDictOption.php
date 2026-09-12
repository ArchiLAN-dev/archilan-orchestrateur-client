<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Sessions\Yaml\Option;

/**
 * Represents an OptionCounter, ItemDict, or OptionDict option - and universal dict options
 * such as start_inventory (item name → quantity).
 */
final readonly class ItemDictOption implements OptionValue
{
    /**
     * @param array<string, int> $items
     */
    public function __construct(
        public string $key,
        public array $items,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * An empty dict option is returned as an object, never as `[]`.
     *
     * PHP cannot tell an empty list from an empty map, and both the YAML dumper and `json_encode`
     * default an empty array to the *sequence* form. Archipelago's `OptionDict::from_any` rejects
     * anything but a mapping ("Cannot Convert from non-dictionary"), so `start_inventory: []` would
     * fail generation outright. See {@see \Archilan\OrchestratorClient\Sessions\Yaml\PlayerYaml}
     * for the empty-collection convention this upholds.
     *
     * @return array<string, int>|\stdClass
     */
    public function jsonSerialize(): array|\stdClass
    {
        return [] === $this->items ? new \stdClass() : $this->items;
    }
}
