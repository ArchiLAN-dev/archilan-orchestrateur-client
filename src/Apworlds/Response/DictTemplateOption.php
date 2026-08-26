<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Apworlds\Response;

/**
 * An Archipelago `OptionDict`: a mapping of named sub-settings to **literal** values.
 *
 * Deliberately not a {@see WeightsTemplateOption}, which it looks exactly like on the wire. A
 * weighted option maps each of its values to an integer weight; a dict option maps a setting name to
 * whatever that setting is worth - a string, a number, a bool, or a nested block.
 *
 * Reading one as the other is not cosmetic. A consumer that assumes weights runs every value through
 * an integer coercion, which turned Pokemon Platinum's `default_player_name: player_name` into
 * `default_player_name: 0` and crashed generation with "TypeError: 'int' object is not iterable".
 *
 * Sub-values are typed `mixed` on purpose: Slay the Spire's `advanced_characters` maps a character
 * name to a block of its own settings, so flattening them to scalars would lose the nesting.
 */
final readonly class DictTemplateOption extends TemplateOption
{
    /**
     * @param array<string, mixed> $defaults  the sub-settings and their literal default values
     * @param string[]             $validKeys the sub-settings the option accepts, when introspection
     *                                        knows them; empty when it does not
     */
    public function __construct(
        string $key,
        string $description,
        public array $defaults,
        public array $validKeys,
    ) {
        parent::__construct($key, $description);
    }

    /** @param array<string, mixed> $data */
    public static function fromData(string $key, string $description, array $data): self
    {
        $defaults = [];
        if (isset($data['defaultValue']) && is_array($data['defaultValue'])) {
            foreach ($data['defaultValue'] as $subKey => $value) {
                if (is_string($subKey)) {
                    $defaults[$subKey] = $value;
                }
            }
        }

        $validKeys = [];
        if (isset($data['validKeys']) && is_array($data['validKeys'])) {
            $validKeys = array_values(array_filter($data['validKeys'], 'is_string'));
        }

        return new self($key, $description, $defaults, $validKeys);
    }
}
