<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Sessions;

use Archilan\OrchestratorClient\Exception\NotFoundException;
use Archilan\OrchestratorClient\Exception\OrchestratorException;
use Archilan\OrchestratorClient\Exception\RequiredFileExpiredException;
use Archilan\OrchestratorClient\Exception\SessionNotFoundException;
use Archilan\OrchestratorClient\Http\HttpTransport;
use Archilan\OrchestratorClient\Sessions\Request\ConfigureRequest;
use Archilan\OrchestratorClient\Sessions\Request\PreflightRequest;
use Archilan\OrchestratorClient\Sessions\Response\ConfigureResult;
use Archilan\OrchestratorClient\Sessions\Response\PreflightResult;
use Archilan\OrchestratorClient\Sessions\Response\SessionLogs;
use Archilan\OrchestratorClient\Sessions\Response\SessionResponse;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

final class SessionsClient
{
    public function __construct(private readonly HttpTransport $transport)
    {
    }

    /**
     * @param array<string, mixed> $generationOptions optional generator options forwarded as-is
     *                                                 (e.g. plandoOptions, race, spoiler)
     * @param list<string>         $requiredFileIds   files held by requiredFiles()->stage() that this
     *                                                 generation consumes (story 44.2)
     *
     * @throws RequiredFileExpiredException when one of them is no longer held
     */
    public function generate(string $sessionId, string $adminPassword, ?string $seed = null, array $generationOptions = [], array $requiredFileIds = []): void
    {
        $body = ['adminPassword' => $adminPassword];
        if (null !== $seed) {
            $body['seed'] = $seed;
        }
        foreach ($generationOptions as $key => $value) {
            $body[$key] = $value;
        }
        if ([] !== $requiredFileIds) {
            $body['requiredFileIds'] = $requiredFileIds;
        }
        $this->transport->postVoid("/sessions/{$sessionId}/generate", $body);
    }

    /**
     * @param array<string, scalar> $serverOptions optional server_options forwarded as-is
     */
    public function launch(string $sessionId, string $adminPassword, ?string $serverPassword = null, array $serverOptions = []): void
    {
        $body = ['adminPassword' => $adminPassword];
        if (null !== $serverPassword) {
            $body['serverPassword'] = $serverPassword;
        }
        foreach ($serverOptions as $key => $value) {
            $body[$key] = $value;
        }
        $this->transport->postVoid("/sessions/{$sessionId}/launch", $body);
    }

    /**
     * @param array<string, scalar> $serverOptions optional server_options; appended as string
     *                                             multipart fields (the launch-from-file form is string-only)
     */
    public function launchFromFile(
        string $sessionId,
        string $fileContents,
        string $filename,
        string $adminPassword,
        ?string $serverPassword = null,
        array $serverOptions = [],
    ): void {
        $fields = [
            'file' => new DataPart($fileContents, $filename, 'application/octet-stream'),
            'adminPassword' => $adminPassword,
        ];
        if (null !== $serverPassword) {
            $fields['serverPassword'] = $serverPassword;
        }
        foreach ($serverOptions as $key => $value) {
            $fields[$key] = self::toFormValue($value);
        }
        $this->transport->postMultipartVoid(
            "/sessions/{$sessionId}/launch-from-file",
            new FormDataPart($fields),
        );
    }

    private static function toFormValue(string|int|float|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    public function stop(string $sessionId): void
    {
        $this->transport->postVoid("/sessions/{$sessionId}/stop");
    }

    public function restart(string $sessionId): void
    {
        $this->transport->postVoid("/sessions/{$sessionId}/restart");
    }

    /**
     * Resume a session that went idle via Archipelago's auto_shutdown: the orchestrateur
     * relaunches the AP server on the retained session volume so the latest save is reloaded.
     */
    public function relaunchFromSave(string $sessionId): void
    {
        $this->transport->postVoid("/sessions/{$sessionId}/relaunch-from-save");
    }

    public function get(string $sessionId): SessionResponse
    {
        return SessionResponse::fromArray($this->transport->getJson("/sessions/{$sessionId}"));
    }

    /**
     * GET /sessions/{sessionId}/logs (story 17.33): the last lines of one of the session's containers.
     *
     * @param 'ap'|'bridge'|'gen' $container the Archipelago server, the bridge or the generation
     * @param int                  $tail      number of lines, 1 to 2000
     *
     * @throws NotFoundException    when the orchestrator predates story 17.33
     * @throws OrchestratorException on an invalid container or tail, or a Docker failure
     */
    public function logs(string $sessionId, string $container, int $tail = 200): SessionLogs
    {
        $query = http_build_query(['container' => $container, 'tail' => $tail]);
        try {
            $data = $this->transport->getJson("/sessions/{$sessionId}/logs?{$query}");
        } catch (SessionNotFoundException $e) {
            // The route never answers 404: a 404 is an orchestrator without it.
            throw new NotFoundException('GET /sessions/{id}/logs is not available on this orchestrator (older than story 17.33?)', 0, $e);
        }

        return SessionLogs::fromArray($data);
    }

    public function delete(string $sessionId): void
    {
        $this->transport->deleteVoid("/sessions/{$sessionId}");
    }

    public function preflight(string $sessionId, PreflightRequest $request): PreflightResult
    {
        return PreflightResult::fromArray(
            $this->transport->postJson("/sessions/{$sessionId}/preflight", $request),
        );
    }

    public function configure(string $sessionId, ConfigureRequest $request): ConfigureResult
    {
        return ConfigureResult::fromArray(
            $this->transport->postJson("/sessions/{$sessionId}/configure", $request),
        );
    }
}
