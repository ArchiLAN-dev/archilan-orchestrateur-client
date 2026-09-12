<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Sessions\Yaml;

use Archilan\OrchestratorClient\Sessions\Yaml\Option\OptionValue;
use Symfony\Component\Yaml\Yaml;

final readonly class PlayerYaml
{
    /**
     * Dump flags that keep an empty collection's kind intact across the round trip.
     *
     * PHP has a single empty value for both YAML shapes, so `Yaml::dump()` has to be told which
     * one to write. Its default writes `{  }` for *every* empty array, which silently rewrites an
     * empty sequence into an empty mapping - and Archipelago reads the two very differently. In
     * Starcraft 2 for instance, `custom_mission_order` tells a layout from a plain setting by
     * `type(val) == dict`, so an `entry_rules: []` arriving as `entry_rules: {}` is promoted to a
     * layout and generation dies on `should be instance of 'list'`.
     *
     * The convention this fixes in place, for every value reaching `toArray()`:
     *  - an empty `array`    is written `[]`   (empty sequence);
     *  - an empty `stdClass` is written `{  }` (empty mapping), which needs DUMP_OBJECT_AS_MAP,
     *    without which an object dumps as `null`.
     */
    private const DUMP_FLAGS = Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE;

    /**
     * @param OptionValue[] $options Game-specific and universal options for this slot.
     */
    public function __construct(
        public string $name,
        public string $game,
        public array $options = [],
        public ?string $description = null,
    ) {
    }

    public function toYamlString(): string
    {
        $yaml = Yaml::dump($this->toArray(), 4, 2, self::DUMP_FLAGS);

        // PHP coerces a canonical-integer string array key ("2048") to int, so Yaml::dump
        // emits the game section key unquoted and the Archipelago generator parses it back
        // as an int, which never matches the (string) `game:` value ("No game options for
        // selected game found"). Re-quote the section key. Only the section key can match
        // at column 0: option lines below it are indented.
        if ((string) (int) $this->game === $this->game) {
            $quoted = preg_replace(
                '/^'.preg_quote($this->game, '/').':/m',
                sprintf("'%s':", $this->game),
                $yaml,
                1,
            );
            if (null !== $quoted) {
                $yaml = $quoted;
            }
        }

        return $yaml;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'game' => $this->game,
        ];

        if (null !== $this->description) {
            $data['description'] = $this->description;
        }

        $gameSection = [];
        foreach ($this->options as $option) {
            $gameSection[$option->getKey()] = $option->jsonSerialize();
        }
        if ([] !== $gameSection) {
            $data[$this->game] = $gameSection;
        }

        return $data;
    }
}
