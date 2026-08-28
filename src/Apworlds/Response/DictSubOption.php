<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Apworlds\Response;

/**
 * What one sub-setting of an {@see DictTemplateOption} accepts.
 *
 * Populated only when the apworld's option class declares a `schema` for it. An `OptionDict`
 * carries no value vocabulary of its own, so for most worlds there is simply no entry - which is
 * the difference between "the world declares nothing" and "the world declares an empty list".
 */
final readonly class DictSubOption
{
    /** @param list<string> $values the values this sub-setting accepts */
    public function __construct(
        public array $values,
    ) {
    }

    /** @param array<mixed, mixed> $data one entry of the response's `keys` map */
    public static function fromData(array $data): ?self
    {
        if (!isset($data['values']) || !is_array($data['values'])) {
            return null;
        }

        $values = array_values(array_filter($data['values'], is_string(...)));

        // One value is not a choice, and an empty list is not a vocabulary: in both cases the
        // consumer is better served by the absence, which leaves its free text field alone.
        return count($values) > 1 ? new self($values) : null;
    }
}
