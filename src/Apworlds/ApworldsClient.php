<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Apworlds;

use Archilan\OrchestratorClient\Apworlds\Response\ApworldEntry;
use Archilan\OrchestratorClient\Apworlds\Response\ApworldItems;
use Archilan\OrchestratorClient\Apworlds\Response\ApworldPreflight;
use Archilan\OrchestratorClient\Apworlds\Response\TemplateOption;
use Archilan\OrchestratorClient\Apworlds\Response\UploadApworldResult;
use Archilan\OrchestratorClient\Exception\RequiredFileExpiredException;
use Archilan\OrchestratorClient\Http\HttpTransport;
use Archilan\OrchestratorClient\RequiredFiles\Response\DeclaredFile;
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
     * Static item names and item groups introspected from the apworld's World class (story 9.58), for the
     * options that take items (start_hints, start_inventory, local_items...). A hint like the locations.
     */
    public function getItems(string $hash): ApworldItems
    {
        return ApworldItems::fromArray($this->transport->getJson("/apworlds/{$hash}/items"));
    }

    /**
     * The files the apworld's world declares in its host.yaml settings (story 44.3), read at
     * introspection. Empty until the apworld is (re)introspected.
     *
     * @return list<DeclaredFile>
     */
    public function getDeclaredFiles(string $hash): array
    {
        return DeclaredFile::listFrom($this->transport->getJson("/apworlds/{$hash}/required-files")['requiredFiles'] ?? null);
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
     *
     * @param list<string> $requiredFileIds files held by requiredFiles()->stage() that the test reads, a ROM
     *                                      provided by an admin (story 44.6); the verdict then says withRequiredFiles
     *
     * @throws RequiredFileExpiredException when one of them is no longer held
     */
    public function runPreflight(string $hash, array $requiredFileIds = []): ApworldPreflight
    {
        $body = [] !== $requiredFileIds ? ['requiredFileIds' => $requiredFileIds] : [];

        return $this->preflightFromResponse($this->transport->postJson("/apworlds/{$hash}/preflight", $body));
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
