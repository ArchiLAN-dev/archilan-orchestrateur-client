<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\RequiredFiles;

use Archilan\OrchestratorClient\Exception\OrchestratorException;
use Archilan\OrchestratorClient\Exception\ServiceUnavailableException;
use Archilan\OrchestratorClient\Http\HttpTransport;
use Archilan\OrchestratorClient\RequiredFiles\Response\StagedRequiredFile;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

/**
 * Files a world reads at generation, almost always a ROM (story 44.2). The orchestrator holds one in
 * memory until the generation that names its id takes it, then drops it with that generation's
 * container: nothing is stored.
 */
final class RequiredFilesClient
{
    public function __construct(private readonly HttpTransport $transport)
    {
    }

    /**
     * POST /required-files. The file is streamed from $path, never loaded whole in memory.
     *
     * @param string $setting the host.yaml setting the generation names, e.g. DK3Settings.rom_file
     *
     * @throws ServiceUnavailableException when the orchestrator already holds too many files
     * @throws OrchestratorException       when the file is refused (too large, invalid setting)
     */
    public function stage(string $setting, string $path, string $filename): StagedRequiredFile
    {
        $form = new FormDataPart([
            'setting' => $setting,
            'file' => DataPart::fromPath($path, $filename, 'application/octet-stream'),
        ]);

        return StagedRequiredFile::fromArray($this->transport->postMultipartStreamedJson('/required-files', $form));
    }

    /** DELETE /required-files/{id}: the launch it was held for was abandoned. Idempotent. */
    public function release(string $stagedFileId): void
    {
        $this->transport->deleteVoid('/required-files/'.rawurlencode($stagedFileId));
    }
}
