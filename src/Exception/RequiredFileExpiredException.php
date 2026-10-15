<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Exception;

/**
 * Story 44.2: a generation named a held file the orchestrator no longer has (expired after 30
 * minutes, already used, or lost to a restart). The launcher has to send it again.
 */
final class RequiredFileExpiredException extends OrchestratorException
{
}
