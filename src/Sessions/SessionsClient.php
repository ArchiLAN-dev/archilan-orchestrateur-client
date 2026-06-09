<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Sessions;

use Archilan\OrchestratorClient\Http\HttpTransport;
use Archilan\OrchestratorClient\Sessions\Request\ConfigureRequest;
use Archilan\OrchestratorClient\Sessions\Request\PreflightRequest;
use Archilan\OrchestratorClient\Sessions\Response\ConfigureResult;
use Archilan\OrchestratorClient\Sessions\Response\PreflightResult;
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
     */
    public function generate(string $sessionId, string $adminPassword, ?string $seed = null, array $generationOptions = []): void
    {
        $body = ['adminPassword' => $adminPassword];
        if (null !== $seed) {
            $body['seed'] = $seed;
        }
        foreach ($generationOptions as $key => $value) {
            $body[$key] = $value;
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

    public function get(string $sessionId): SessionResponse
    {
        return SessionResponse::fromArray($this->transport->getJson("/sessions/{$sessionId}"));
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
