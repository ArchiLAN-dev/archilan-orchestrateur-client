<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Apworlds;

use Archilan\OrchestratorClient\Apworlds\Response\ApworldEntry;
use Archilan\OrchestratorClient\Apworlds\Response\ApworldPreflight;
use Archilan\OrchestratorClient\Apworlds\Response\TemplateOption;
use Archilan\OrchestratorClient\Apworlds\Response\UploadApworldResult;
use Archilan\OrchestratorClient\Http\HttpTransport;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

/** @implements \IteratorAggregate<int, ApworldEntry> */
final class ApworldsClient implements \IteratorAggregate
{
    public function __construct(private readonly HttpTransport $transport)
    {
    }

    /** @return \ArrayIterator<int, ApworldEntry> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->list());
    }

    public function upload(string $fileContents, string $filename): UploadApworldResult
    {
        $form = new FormDataPart([
            'file' => new DataPart($fileContents, $filename, 'application/octet-stream'),
        ]);

        return UploadApworldResult::fromArray($this->transport->postMultipartJson('/apworlds', $form));
    }

    public function getYamlTemplate(string $hash): string
    {
        return $this->transport->getRaw("/apworlds/{$hash}/yaml");
    }

    /**
     * @return TemplateOption[]
     */
    public function getOptions(string $hash): array
    {
        $data = $this->transport->getJson("/apworlds/{$hash}/options");
        $options = [];
        $rawOptions = $data['options'] ?? null;
        foreach (is_array($rawOptions) ? $rawOptions : [] as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $options[] = TemplateOption::fromArray($item);
            }
        }

        return $options;
    }

    /**
     * Static location names introspected from the apworld's World class.
     *
     * The full nameable-location set (location_name_to_id keys) these YAML options match against;
     * empty until the apworld has been introspected. It is the static list - options-dependent checks
     * are not reflected, so consumers use it as a free-text suggestion hint, not a source of truth.
     *
     * @return list<string>
     */
    public function getLocations(string $hash): array
    {
        $data = $this->transport->getJson("/apworlds/{$hash}/locations");
        $locations = [];
        $raw = $data['locations'] ?? null;
        foreach (is_array($raw) ? $raw : [] as $item) {
            if (is_string($item)) {
                $locations[] = $item;
            }
        }

        return $locations;
    }

    /**
     * Replace the YAML template stored next to the apworld (story 9.45). The upload
     * preflight reads that file, so keeping it in sync with what the platform serves to
     * players is what makes the verdict meaningful. Returns the stored template.
     */
    public function setYamlTemplate(string $hash, string $template): string
    {
        $data = $this->transport->putJson("/apworlds/{$hash}/yaml", ['template' => $template]);

        return is_string($data['template'] ?? null) ? $data['template'] : $template;
    }

    /**
     * Regenerate the template from the apworld already in storage (story 9.46): undoes an
     * edit, and repairs a game whose template failed at upload. Throws when the world still
     * cannot produce one - the stored template is then left untouched.
     */
    public function regenerateYamlTemplate(string $hash): string
    {
        $data = $this->transport->postJson("/apworlds/{$hash}/template");

        return is_string($data['template'] ?? null) ? $data['template'] : '';
    }

    /**
     * Re-run option introspection on the apworld already in storage (story 9.53).
     *
     * Introspection otherwise runs exactly once, when the apworld is uploaded, so a world
     * introspected by an older image keeps that answer for good - and the only way to refresh it
     * was to re-upload the very bytes the server already holds.
     *
     * Throws when the world cannot be introspected; the stored sidecar is then left untouched,
     * which matters because it carries the range bounds, the option types and the location list,
     * not just the newest field.
     */
    public function reintrospect(string $hash): void
    {
        $this->transport->postVoid("/apworlds/{$hash}/introspect");
    }

    /**
     * Re-run the upload-time preflight test generation (story 9.38). The check is
     * asynchronous on the orchestrator: the returned verdict is "pending"; poll list()
     * for the final one.
     */
    public function runPreflight(string $hash): ApworldPreflight
    {
        return $this->preflightFromResponse($this->transport->postJson("/apworlds/{$hash}/preflight"));
    }

    /** Toggle the admin "force allow" override on a failed preflight verdict (story 9.38). */
    public function overridePreflight(string $hash, bool $overridden): ApworldPreflight
    {
        return $this->preflightFromResponse(
            $this->transport->postJson("/apworlds/{$hash}/preflight-override", ['overridden' => $overridden]),
        );
    }

    /** @param array<string, mixed> $data */
    private function preflightFromResponse(array $data): ApworldPreflight
    {
        $raw = $data['preflight'] ?? null;
        /** @var array<string, mixed> $raw */
        $raw = is_array($raw) ? $raw : [];

        return ApworldPreflight::fromArray($raw);
    }

    /**
     * @return ApworldEntry[]
     */
    public function list(): array
    {
        $data = $this->transport->getJson('/apworlds');
        $entries = [];
        $rawApworlds = $data['apworlds'] ?? null;
        foreach (is_array($rawApworlds) ? $rawApworlds : [] as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $entries[] = ApworldEntry::fromArray($item);
            }
        }

        return $entries;
    }
}
