<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Sessions\Yaml;

use Archilan\OrchestratorClient\Sessions\Yaml\Option\OptionValue;
use Symfony\Component\Yaml\Yaml;

final readonly class PlayerYaml
{
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
        $yaml = Yaml::dump($this->toArray(), 4, 2);

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
