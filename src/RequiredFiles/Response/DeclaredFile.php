<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\RequiredFiles\Response;

use Archilan\OrchestratorClient\Support\ResponseFields;

/**
 * A file a world declares in its host.yaml settings (story 44.3), almost always a ROM. A superset of
 * what a generation needs: most only serve the client.
 */
final readonly class DeclaredFile
{
    /** @param list<string> $md5s accepted MD5s, lowercase hex; empty when the world checks none */
    public function __construct(
        public string $setting,
        public string $file,
        public string $extension,
        public ?string $description,
        public array $md5s,
    ) {
    }

    /** @param array<mixed> $data null when the entry names no setting */
    public static function fromArray(array $data): ?self
    {
        $fields = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $fields[$key] = $value;
            }
        }
        $setting = ResponseFields::optionalString($fields, 'setting');
        if (null === $setting) {
            return null;
        }
        $md5s = [];
        $raw = $fields['md5s'] ?? null;
        foreach (is_array($raw) ? $raw : [] as $md5) {
            if (is_string($md5) && '' !== $md5) {
                $md5s[] = strtolower($md5);
            }
        }

        return new self(
            $setting,
            ResponseFields::optionalString($fields, 'file') ?? '',
            ResponseFields::optionalString($fields, 'extension') ?? '',
            ResponseFields::optionalString($fields, 'description'),
            $md5s,
        );
    }

    /**
     * @param mixed $raw a JSON list of entries
     *
     * @return list<self>
     */
    public static function listFrom(mixed $raw): array
    {
        $files = [];
        foreach (is_array($raw) ? $raw : [] as $entry) {
            if (is_array($entry) && null !== ($file = self::fromArray($entry))) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
