<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Support;

/**
 * @internal
 *
 * Reads optional fields of orchestrator responses the one way (story 38.8 review): the orchestrator
 * omits empty fields, so an absent key and an empty string both mean "not known".
 */
final class ResponseFields
{
    /** @param array<string, mixed> $data */
    public static function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && '' !== $value ? $value : null;
    }
}
